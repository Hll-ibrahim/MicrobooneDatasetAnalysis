#!/usr/bin/env python3
"""
MicroBooNE HDF5 yardımcı aracı — Laravel "MicroBooNE H5 Portalı" için.

PHP'nin olgun bir HDF5 okuyucusu olmadığından .h5 dosyası burada h5py ile
okunur; PostgreSQL'e yazma işi Laravel tarafında (COPY) yapılır.

Kullanım
--------
  python3 h5_tool.py check
  python3 h5_tool.py inspect DOSYA.h5
  python3 h5_tool.py export  DOSYA.h5 --out KLASOR [--offset 0] [--max-events 200]
                             [--groups event_table,hit_table,...] [--chunk 1000000]

inspect : Dosyanın grup/sütun şemasını tek bir JSON nesnesi olarak yazar.
export  : Seçilen olay aralığındaki satırları her grup için bir TSV dosyasına
          (PostgreSQL COPY metin biçimi, NULL = \\N) yazar. İlerleme ve sonuç
          stdout'a satır başına bir JSON nesnesi olarak akar:
              {"event": "progress", ...}
              {"event": "done", ...}   veya   {"event": "error", "message": ...}

Veri modeli (Zenodo 7261921, NoWire)
-----------------------------------
* Her grup (event_table, hit_table, ...) sütun başına bir dataset içerir.
* Ortak anahtar: event_id = (run, subrun, event), şekil (N, 3).
* event_id.seq_cnt (M, 2): [olay sıra no, o olaya ait satır sayısı].
  Bu sayede tüm tablo belleğe alınmadan bir olay aralığı okunabilir.
* (N, 3) şekilli sütunlar üç sütuna açılır: adında "wire" geçenler
  _u/_v/_y (düzlem), diğerleri _x/_y/_z (uzay) son ekini alır.
"""

import argparse
import csv
import json
import os
import sys
import traceback

import numpy as np

try:
    import h5py
except ImportError:  # pragma: no cover
    sys.stdout.write(json.dumps({"event": "error", "message": "h5py kurulu değil: pip install h5py"}) + "\n")
    sys.exit(2)

try:
    import pandas as pd
except ImportError:  # pandas yoksa daha yavaş saf numpy yazıcı kullanılır
    pd = None

EVENT_ID = "event_id"
SEQ_CNT = "event_id.seq_cnt"
NULL = "\\N"

# Aktarım sırası: event_table her zaman ilk (diğer tablolar ona bağlanır)
GROUP_ORDER = [
    "event_table",
    "hit_table",
    "edep_table",
    "particle_table",
    "pandoraPrimary_table",
    "pandoraPfp_table",
    "pandoraHit_table",
    "opflash_table",
    "opflashsumpe_table",
    "ophit_table",
]


# ---------------------------------------------------------------------------
# Yardımcılar
# ---------------------------------------------------------------------------
def emit(obj):
    sys.stdout.write(json.dumps(obj, ensure_ascii=False) + "\n")
    sys.stdout.flush()


def width_of(shape):
    if len(shape) < 2:
        return 1
    return int(np.prod(shape[1:]))


def out_names(name, shape):
    """Bir dataset'in TSV'de hangi sütun adlarıyla temsil edileceği."""
    k = width_of(shape)
    if k == 1:
        return [name]
    if k == 3:
        suffixes = ("u", "v", "y") if "wire" in name else ("x", "y", "z")
        return [f"{name}_{s}" for s in suffixes]
    return [f"{name}_{i}" for i in range(k)]


def kind_of(dtype):
    k = np.dtype(dtype).kind
    if k == "b":
        return "bool"
    if k in "iu":
        return "int"
    if k == "f":
        return "float"
    return "str"


def pack_keys(eid):
    """(run, subrun, event) -> tek int64 anahtar."""
    eid = np.asarray(eid, dtype=np.int64).reshape(len(eid), -1)
    return (eid[:, 0] << 40) + (eid[:, 1] << 20) + eid[:, 2]


def data_datasets(group):
    """event_id dışındaki sütun dataset'leri (ad sırasıyla)."""
    out = []
    for name in sorted(group.keys()):
        if name in (EVENT_ID, SEQ_CNT):
            continue
        ds = group[name]
        if isinstance(ds, h5py.Dataset):
            out.append((name, ds))
    return out


def decode_attr(v):
    if isinstance(v, bytes):
        return v.decode("utf-8", "replace")
    if isinstance(v, np.ndarray):
        return [decode_attr(x) for x in v.tolist()]
    if isinstance(v, np.generic):
        return v.item()
    return v if isinstance(v, (str, int, float, bool, list)) or v is None else str(v)


# ---------------------------------------------------------------------------
# inspect
# ---------------------------------------------------------------------------
def cmd_inspect(path):
    info = {
        "file": os.path.abspath(path),
        "size_bytes": os.path.getsize(path),
        "groups": [],
        "attrs": {},
    }
    with h5py.File(path, "r") as f:
        info["attrs"] = {k: decode_attr(v) for k, v in f.attrs.items()}
        for gname in sorted(f.keys()):
            g = f[gname]
            if not isinstance(g, h5py.Group):
                continue
            cols, outs = [], []
            for dname in sorted(g.keys()):
                ds = g[dname]
                if not isinstance(ds, h5py.Dataset):
                    continue
                cols.append({"name": dname, "shape": [int(s) for s in ds.shape], "dtype": str(ds.dtype)})
                if dname not in (EVENT_ID, SEQ_CNT):
                    outs.extend(out_names(dname, ds.shape))
            rows = int(g[EVENT_ID].shape[0]) if EVENT_ID in g else (cols[0]["shape"][0] if cols and cols[0]["shape"] else 0)
            events = int(g[SEQ_CNT].shape[0]) if SEQ_CNT in g else None
            info["groups"].append({
                "name": gname,
                "rows": rows,
                "events": events,
                "columns": cols,
                "output_columns": outs,
            })
        info["n_events"] = int(f["event_table"][EVENT_ID].shape[0]) if "event_table" in f else None
    print(json.dumps(info, ensure_ascii=False))


# ---------------------------------------------------------------------------
# export
# ---------------------------------------------------------------------------
def seq_bounds_for_event_rows(et, lo, hi):
    """event_table satır aralığı [lo, hi) için seq numarası sınırları."""
    if SEQ_CNT not in et:
        return None
    sc = np.asarray(et[SEQ_CNT][()], dtype=np.int64).reshape(-1, 2)
    seq_per_row = np.repeat(sc[:, 0], sc[:, 1])
    if len(seq_per_row) != et[EVENT_ID].shape[0]:
        return None
    return int(seq_per_row[lo]), int(seq_per_row[hi - 1])


def row_range(group, seq_bounds, sel_keys):
    """
    Gruptaki satır aralığını seq_cnt ile bulur. Doğrulanamazsa None döner
    (çağıran taraf tam taramaya geçer).
    """
    n = int(group[EVENT_ID].shape[0])
    if seq_bounds is None or SEQ_CNT not in group:
        return None
    sc = np.asarray(group[SEQ_CNT][()], dtype=np.int64).reshape(-1, 2)
    seq, cnt = sc[:, 0], sc[:, 1]
    if cnt.sum() != n or (len(seq) > 1 and np.any(np.diff(seq) < 0)):
        return None
    starts = np.concatenate([[0], np.cumsum(cnt)[:-1]])
    m = (seq >= seq_bounds[0]) & (seq <= seq_bounds[1])
    if not m.any():
        return (0, 0)
    idx = np.nonzero(m)[0]
    a, b = int(starts[idx[0]]), int(starts[idx[-1]] + cnt[idx[-1]])
    # Sınır doğrulaması: aralığın hemen dışındaki satırlar seçili olmamalı
    eid = group[EVENT_ID]
    for r in (a - 1, b):
        if 0 <= r < n and np.isin(pack_keys(eid[r:r + 1]), sel_keys).any():
            return None
    return (a, b)


def to_column_arrays(name, raw, n):
    """Ham dataset dilimini (n, k) -> k adet 1B diziye çevirir."""
    arr = np.asarray(raw)
    arr = arr.reshape(n, -1) if n else arr.reshape(0, max(1, width_of(arr.shape)))
    k = arr.shape[1]
    kind = kind_of(arr.dtype)
    cols = []
    for j in range(k):
        c = arr[:, j]
        if kind == "bool":
            c = c.astype(np.int8)
        elif kind == "float" and c.dtype.itemsize == 4:
            # float32: en kısa ondalık gösterim (0.44119605) — dosya ~%40 küçülür
            s = c.astype(str).astype(object)
            s[np.isnan(c)] = NULL
            c = s
        elif kind == "str":
            c = np.array([sanitize(x) for x in c], dtype=object)
        cols.append(c)
    return cols, kind


def sanitize(x):
    if isinstance(x, bytes):
        x = x.decode("utf-8", "replace")
    x = str(x).rstrip("\x00")
    # COPY metin biçiminde özel anlamı olan karakterleri temizle
    return x.replace("\\", "/").replace("\t", " ").replace("\n", " ").replace("\r", " ").replace('"', "'")


def write_block(fh, names, columns):
    if not columns or len(columns[0]) == 0:
        return
    if pd is not None:
        df = pd.DataFrame({nm: col for nm, col in zip(names, columns)}, columns=names)
        kwargs = dict(sep="\t", header=False, index=False, na_rep=NULL, quoting=csv.QUOTE_NONE)
        try:
            df.to_csv(fh, lineterminator="\n", **kwargs)
        except TypeError:  # pandas < 1.5
            df.to_csv(fh, line_terminator="\n", **kwargs)
        return
    # pandas yoksa: saf numpy/python yazıcı
    str_cols = []
    for col in columns:
        if col.dtype.kind == "f":
            s = np.char.mod("%.9g", col).astype(object)
            s[np.isnan(col)] = NULL
        else:
            s = col.astype(str)
        str_cols.append(s)
    fh.write("\n".join("\t".join(r) for r in zip(*str_cols)))
    fh.write("\n")


def export_group(f, gname, out_dir, sel_keys, seq_bounds, chunk, gi, gcount):
    g = f[gname]
    n = int(g[EVENT_ID].shape[0])
    dsets = data_datasets(g)
    names, kinds = [], {}
    for dname, ds in dsets:
        for nm in out_names(dname, ds.shape):
            names.append(nm)
            kinds[nm] = kind_of(ds.dtype)

    rng = row_range(g, seq_bounds, sel_keys)
    scan = "seq_cnt"
    if rng is None:
        rng, scan = (0, n), "full_scan"
    a, b = rng
    total = max(0, b - a)

    path = os.path.join(out_dir, f"{gname}.tsv")
    written = 0
    with open(path, "w", encoding="utf-8", newline="") as fh:
        fh.write("\t".join(["run", "subrun", "event"] + names) + "\n")
        s = a
        while s < b:
            e = min(b, s + chunk)
            m_len = e - s
            eid = np.asarray(g[EVENT_ID][s:e], dtype=np.int64).reshape(m_len, -1)[:, :3]
            mask = np.isin(pack_keys(eid), sel_keys)
            if mask.any():
                take_all = bool(mask.all())
                cols = [eid[:, 0], eid[:, 1], eid[:, 2]]
                for dname, ds in dsets:
                    parts, _ = to_column_arrays(dname, ds[s:e], m_len)
                    cols.extend(parts)
                if not take_all:
                    cols = [c[mask] for c in cols]
                write_block(fh, ["run", "subrun", "event"] + names, cols)
                written += int(mask.sum())
            s = e
            emit({
                "event": "progress",
                "group": gname,
                "group_index": gi,
                "group_count": gcount,
                "rows_done": s - a,
                "rows_total": total,
            })
    return {
        "file": os.path.basename(path),
        "rows": written,
        "columns": names,
        "kinds": kinds,
        "scan": scan,
    }


def cmd_export(path, out_dir, offset, max_events, groups, chunk):
    os.makedirs(out_dir, exist_ok=True)
    with h5py.File(path, "r") as f:
        if "event_table" not in f:
            raise RuntimeError("Dosyada event_table grubu yok; MicroBooNE NuML biçimi bekleniyor.")
        et = f["event_table"]
        n_ev = int(et[EVENT_ID].shape[0])
        lo = max(0, min(int(offset), n_ev))
        hi = n_ev if max_events is None else min(n_ev, lo + int(max_events))
        if hi <= lo:
            raise RuntimeError(f"Seçilen aralıkta olay yok (offset={offset}, dosyadaki olay={n_ev}).")

        sel_eid = np.asarray(et[EVENT_ID][lo:hi], dtype=np.int64).reshape(hi - lo, -1)[:, :3]
        sel_keys = np.unique(pack_keys(sel_eid))
        seq_bounds = seq_bounds_for_event_rows(et, lo, hi)

        requested = groups or GROUP_ORDER
        if "event_table" not in requested:
            requested = ["event_table"] + list(requested)
        ordered = [g for g in GROUP_ORDER if g in requested] + [g for g in requested if g not in GROUP_ORDER]
        present = [g for g in ordered if g in f and isinstance(f[g], h5py.Group) and EVENT_ID in f[g]]
        missing = [g for g in ordered if g not in present]

        emit({"event": "start", "file_events": n_ev, "offset": lo, "selected_events": hi - lo, "groups": present})

        manifest = {}
        for gi, gname in enumerate(present):
            manifest[gname] = export_group(f, gname, out_dir, sel_keys, seq_bounds, chunk, gi, len(present))

    emit({
        "event": "done",
        "file_events": n_ev,
        "offset": lo,
        "selected_events": hi - lo,
        "groups": manifest,
        "missing_groups": missing,
    })


# ---------------------------------------------------------------------------
def main(argv=None):
    p = argparse.ArgumentParser(description="MicroBooNE HDF5 -> TSV yardımcı aracı")
    sub = p.add_subparsers(dest="cmd", required=True)

    sub.add_parser("check", help="Python ortamını denetle")

    pi = sub.add_parser("inspect", help="Şemayı JSON olarak yaz")
    pi.add_argument("file")

    pe = sub.add_parser("export", help="Olay aralığını TSV'ye aktar")
    pe.add_argument("file")
    pe.add_argument("--out", required=True)
    pe.add_argument("--offset", type=int, default=0)
    pe.add_argument("--max-events", type=int, default=None)
    pe.add_argument("--groups", default="")
    pe.add_argument("--chunk", type=int, default=1_000_000)

    args = p.parse_args(argv)
    try:
        if args.cmd == "check":
            emit({
                "event": "check",
                "python": sys.version.split()[0],
                "h5py": h5py.__version__,
                "numpy": np.__version__,
                "pandas": pd.__version__ if pd is not None else None,
            })
        elif args.cmd == "inspect":
            cmd_inspect(args.file)
        else:
            groups = [x.strip() for x in args.groups.split(",") if x.strip()]
            cmd_export(args.file, args.out, args.offset, args.max_events, groups, max(1000, args.chunk))
    except Exception as exc:  # hata, Laravel tarafında kullanıcıya gösterilir
        traceback.print_exc(file=sys.stderr)
        emit({"event": "error", "message": f"{type(exc).__name__}: {exc}"})
        return 1
    return 0


if __name__ == "__main__":
    sys.exit(main())
