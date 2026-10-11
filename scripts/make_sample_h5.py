#!/usr/bin/env python3
"""
SENTETİK örnek .h5 üretici — portalı gerçek (GB'lık) dosyalar olmadan denemek için.

Zenodo 7261921 (MicroBooNE νe overlay, NoWire) dosyalarıyla AYNI grup/sütun
yapısını üretir; sayılar fiziksel olarak makul ama tamamen rastgeledir.
Bilimsel analizde KULLANMAYIN.

    python3 scripts/make_sample_h5.py storage/app/h5/ornek_sentetik_nowire.h5 --events 60
"""

import argparse

import h5py
import numpy as np

N_WIRES = (2400, 2400, 3456)      # U, V, Y
N_TICKS = 6400
TICK_PER_CM = 18.2
DET = dict(x=(0.0, 256.35), y=(-116.5, 116.5), z=(0.0, 1036.8))


def wire_pos(y, z):
    """Kabaca (y, z) -> U, V, Y tel koordinatı (0.3 cm tel aralığı)."""
    u = (0.5 * z + 0.866 * (y + 116.5)) / 0.3
    v = (0.5 * z - 0.866 * (y - 116.5)) / 0.3
    w = z / 0.3
    return np.clip([u, v, w], 0, [N_WIRES[0] - 1, N_WIRES[1] - 1, N_WIRES[2] - 1])


def line_hits(rng, plane, w0, t0, dw, dt, n, nu=False):
    s = np.sort(rng.uniform(0, 1, n))
    w = np.clip(np.round(w0 + dw * s + rng.normal(0, 0.4, n)), 0, N_WIRES[plane] - 1).astype(np.int32)
    t = np.clip(t0 + dt * s + rng.normal(0, 2.0, n), 0, N_TICKS - 1).astype(np.float32)
    q = rng.lognormal(4.6 if not nu else 4.9, 0.55, n).astype(np.float32)
    r = rng.uniform(2.0, 6.5, n).astype(np.float32)
    return w, t, q, r


class Table:
    def __init__(self):
        self.cols = {}
        self.eid = []
        self.seq = []

    def add(self, seq, run, sub, evt, **cols):
        n = len(next(iter(cols.values())))
        if n == 0:
            return
        self.eid.append(np.tile([run, sub, evt], (n, 1)).astype(np.int32))
        self.seq.append((seq, n))
        for k, v in cols.items():
            v = np.asarray(v)
            if v.ndim == 1:
                v = v.reshape(-1, 1)
            self.cols.setdefault(k, []).append(v)

    def write(self, f, name):
        g = f.create_group(name)
        g.create_dataset("event_id", data=np.concatenate(self.eid), compression="gzip")
        g.create_dataset("event_id.seq_cnt", data=np.array(self.seq, dtype=np.int64))
        for k, parts in self.cols.items():
            g.create_dataset(k, data=np.concatenate(parts), compression="gzip")


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("out")
    ap.add_argument("--events", type=int, default=60)
    ap.add_argument("--seed", type=int, default=7261921)
    args = ap.parse_args()
    rng = np.random.default_rng(args.seed)

    T = {k: Table() for k in ["event_table", "hit_table", "edep_table", "particle_table",
                              "pandoraPrimary_table", "pandoraPfp_table", "pandoraHit_table",
                              "opflash_table", "opflashsumpe_table", "ophit_table"]}
    run, sub, evt = 5900, 1, 0
    for seq in range(args.events):
        evt += int(rng.integers(1, 40))
        if rng.random() < 0.15:
            sub += 1
        if rng.random() < 0.05:
            run += int(rng.integers(1, 30))
            sub = 1

        # ---- event_table ------------------------------------------------
        e_nu = float(np.clip(rng.gamma(3.2, 0.52), 0.15, 6.0))
        pdg = 12 if rng.random() < 0.972 else -12
        vtx = np.array([rng.uniform(*DET["x"]), rng.uniform(*DET["y"]), rng.uniform(*DET["z"])])
        vcorr = vtx + rng.normal(0, 1.2, 3)
        d = np.array([rng.normal(0, 0.05), rng.normal(0, 0.05), 1.0])
        d /= np.linalg.norm(d)
        wp = wire_pos(vcorr[1], vcorr[2])
        vt = vcorr[0] * TICK_PER_CM + 800
        T["event_table"].add(seq, run, sub, evt,
                             is_cc=np.array([1], np.int32), nu_pdg=np.array([pdg], np.int32),
                             nu_energy=np.array([e_nu], np.float32),
                             lep_energy=np.array([e_nu * rng.uniform(0.35, 0.9)], np.float32),
                             nu_dir=d.reshape(1, 3).astype(np.float32),
                             nu_vtx=vtx.reshape(1, 3).astype(np.float32),
                             nu_vtx_corr=vcorr.reshape(1, 3).astype(np.float32),
                             nu_vtx_wire_pos=wp.reshape(1, 3).astype(np.float32),
                             nu_vtx_wire_time=np.array([vt], np.float32))

        # ---- particles (yalnız nötrino tarafı simüle) -----------------------
        pdgs = [11] + [2212] * int(rng.integers(0, 4)) + [211, -211][: int(rng.integers(0, 3))]
        n_sec = int(rng.integers(10, 60))
        g4 = np.arange(1, len(pdgs) + n_sec + 1, dtype=np.int32)
        parent = np.concatenate([np.zeros(len(pdgs)), rng.integers(1, len(pdgs) + 1, n_sec)]).astype(np.int32)
        allpdg = np.array(pdgs + list(rng.choice([22, 11, -11, 2112, 2212], n_sec, p=[.55, .25, .08, .07, .05])), np.int32)
        npart = len(g4)
        mom = np.where(np.arange(npart) < len(pdgs), rng.uniform(0.2, e_nu, npart), rng.exponential(0.03, npart)).astype(np.float32)
        sp = vtx + rng.normal(0, 3, (npart, 3))
        ep = sp + rng.normal(0, 25, (npart, 3))
        sproc = np.array([b"primary"] * len(pdgs) + list(rng.choice([b"eIoni", b"compt", b"conv", b"phot", b"eBrem", b"hadElastic"], n_sec)), dtype="S16")
        eproc = np.array(list(rng.choice([b"Decay", b"eIoni", b"CoupledTransportation", b"hadInelastic", b"conv", b"phot"], npart)), dtype="S24")
        cat = np.where(np.isin(np.abs(allpdg), [11, 22]), 2, np.where(allpdg == 2212, 1, 0)).astype(np.int32)
        swp = np.array([wire_pos(p[1], p[2]) for p in sp], np.float32)
        ewp = np.array([wire_pos(p[1], p[2]) for p in ep], np.float32)
        T["particle_table"].add(seq, run, sub, evt, g4_id=g4, parent_id=parent, g4_pdg=allpdg, category=cat,
                                instance=rng.integers(0, 6, npart).astype(np.int32), momentum=mom,
                                start_process=sproc, end_process=eproc,
                                start_position=sp.astype(np.float32), start_position_corr=(sp + 0.5).astype(np.float32),
                                end_position=ep.astype(np.float32), end_position_corr=(ep + 0.5).astype(np.float32),
                                start_wire_pos=swp, end_wire_pos=ewp,
                                start_wire_time=(sp[:, 0] * TICK_PER_CM + 800).astype(np.float32),
                                end_wire_time=(ep[:, 0] * TICK_PER_CM + 800).astype(np.float32))

        # ---- hits: kozmik çizgiler + nötrino izleri --------------------------
        W, Tm, Q, R, P, NU = [], [], [], [], [], []
        n_cos = int(rng.integers(6, 14))
        for plane in range(3):
            for _ in range(n_cos):
                n = int(rng.integers(40, 160))
                w0 = rng.uniform(0, N_WIRES[plane]); dw = rng.normal(0, 500)
                t0 = rng.uniform(0, N_TICKS); dt = rng.normal(0, 1800)
                w, t, q, r = line_hits(rng, plane, w0, t0, dw, dt, n)
                W.append(w); Tm.append(t); Q.append(q); R.append(r); P.append(np.full(n, plane)); NU.append(np.zeros(n, bool))
            for k in range(len(pdgs) + 2):
                n = int(rng.integers(25, 120)) if k else int(rng.integers(120, 260))
                ang = rng.uniform(-1, 1)
                w, t, q, r = line_hits(rng, plane, wp[plane], vt, 200 * ang + rng.normal(0, 40), rng.normal(0, 700), n, nu=True)
                W.append(w); Tm.append(t); Q.append(q); R.append(r); P.append(np.full(n, plane)); NU.append(np.ones(n, bool))
        W, Tm, Q, R, P, NU = map(np.concatenate, (W, Tm, Q, R, P, NU))
        nh = len(W)
        hid = np.arange(nh, dtype=np.int32)
        T["hit_table"].add(seq, run, sub, evt, hit_id=hid, integral=Q, local_plane=P.astype(np.int32),
                           local_time=Tm, local_wire=W, rms=R, tpc=np.zeros(nh, np.int32))

        # ---- edep: nötrino hit'leri -> parçacık -------------------------------
        nu_hits = hid[NU]
        ed_g4 = rng.choice(g4[: max(1, len(pdgs) + 5)], len(nu_hits)).astype(np.int32)
        ed_e = (Q[NU] / 180.0 * rng.uniform(0.8, 1.2, len(nu_hits))).astype(np.float32)
        T["edep_table"].add(seq, run, sub, evt, hit_id=nu_hits, g4_id=ed_g4, energy=ed_e,
                            energy_fraction=rng.uniform(0.6, 1.0, len(nu_hits)).astype(np.float32))

        # ---- Pandora ----------------------------------------------------------
        n_sl = int(rng.integers(12, 30))
        nu_slice = int(rng.integers(0, n_sl))
        score = rng.beta(0.6, 6, n_sl)
        score[nu_slice] = rng.beta(6, 1.2) if rng.random() < 0.85 else rng.beta(1, 3)
        score[rng.random(n_sl) < 0.25] = -999.0
        score[nu_slice] = abs(score[nu_slice])
        slv = np.tile(vcorr, (n_sl, 1)) + rng.normal(0, 60, (n_sl, 3))
        slv[nu_slice] = vcorr + rng.normal(0, 1.5, 3)
        T["pandoraPrimary_table"].add(seq, run, sub, evt, slice_id=np.arange(n_sl, dtype=np.int32),
                                      slice_pdg=np.where(np.arange(n_sl) == nu_slice, 12, 13).astype(np.int32),
                                      nu_score=score.astype(np.float32),
                                      flashmatch_score=rng.gamma(2, 4, n_sl).astype(np.float32),
                                      vtx=slv.astype(np.float32),
                                      vtx_wire_pos=np.array([wire_pos(v[1], v[2]) for v in slv], np.float32),
                                      vtx_wire_time=(slv[:, 0] * TICK_PER_CM + 800).astype(np.float32))
        if rng.random() > 0.08:  # bazı olaylarda PFP yok (gerçek veride de seq_cnt olay atlar)
            n_pfp = int(rng.integers(1, 6))
            pv = vcorr + rng.normal(0, 2, (n_pfp, 3))
            T["pandoraPfp_table"].add(seq, run, sub, evt, pfp_id=np.arange(n_pfp, dtype=np.int32),
                                      pfp_pdg=rng.choice([11, 13], n_pfp).astype(np.int32),
                                      trkshr_score=rng.uniform(0, 1, n_pfp).astype(np.float32),
                                      vtx=pv.astype(np.float32),
                                      vtx_wire_pos=np.array([wire_pos(v[1], v[2]) for v in pv], np.float32),
                                      vtx_wire_time=(pv[:, 0] * TICK_PER_CM + 800).astype(np.float32))
        sl = np.where(NU & (rng.random(nh) < 0.9), nu_slice, rng.integers(-1, n_sl, nh)).astype(np.int32)
        T["pandoraHit_table"].add(seq, run, sub, evt, hit_id=hid,
                                  pfp_id=np.where(sl == nu_slice, rng.integers(0, 3, nh), -1).astype(np.int32),
                                  slice_id=sl)

        # ---- optik ------------------------------------------------------------
        n_fl = int(rng.integers(1, 5))
        ftime = np.concatenate([[rng.uniform(3.6, 5.2)], rng.uniform(-1500, 1500, n_fl - 1)])
        tpe = np.concatenate([[rng.gamma(4, 300)], rng.gamma(2, 120, n_fl - 1)])
        fz = np.clip(np.concatenate([[vcorr[2] + rng.normal(0, 40)], rng.uniform(0, 1036, n_fl - 1)]), 0, 1036.8)
        T["opflash_table"].add(seq, run, sub, evt, flash_id=np.arange(n_fl, dtype=np.int32), time=ftime.astype(np.float32),
                               time_width=rng.uniform(0.05, 0.4, n_fl).astype(np.float32), totalpe=tpe.astype(np.float32),
                               wire_pos=np.array([wire_pos(0, z) for z in fz], np.float32),
                               y_center=rng.normal(0, 30, n_fl).astype(np.float32), y_width=rng.uniform(10, 45, n_fl).astype(np.float32),
                               z_center=fz.astype(np.float32), z_width=rng.uniform(30, 150, n_fl).astype(np.float32))
        sf, sch, spe = [], [], []
        for fi in range(n_fl):
            pe = rng.dirichlet(np.ones(32) * 2) * tpe[fi]
            sf.append(np.full(32, fi)); sch.append(np.arange(32)); spe.append(pe)
        sf, sch, spe = map(np.concatenate, (sf, sch, spe))
        ns = len(sf)
        T["opflashsumpe_table"].add(seq, run, sub, evt, sumpe_id=np.arange(ns, dtype=np.int32), flash_id=sf.astype(np.int32),
                                    pmt_channel=sch.astype(np.int32), sumpe=spe.astype(np.float32))
        no = int(rng.integers(60, 200))
        ch = rng.integers(0, 32, no)
        T["ophit_table"].add(seq, run, sub, evt, hit_id=np.arange(no, dtype=np.int32), hit_channel=ch.astype(np.int32),
                             amplitude=rng.gamma(2, 20, no).astype(np.float32), area=rng.gamma(2, 150, no).astype(np.float32),
                             pe=rng.gamma(1.5, 3, no).astype(np.float32), peaktime=rng.uniform(0, 1500, no).astype(np.float32),
                             width=rng.uniform(0.1, 1.2, no).astype(np.float32),
                             sumpe_id=np.where(rng.random(no) < 0.6, rng.integers(0, ns, no), -1).astype(np.int32),
                             wire_pos=np.array([wire_pos(0, z) for z in rng.uniform(0, 1036, no)], np.float32))

    with h5py.File(args.out, "w") as f:
        f.attrs["description"] = "SENTETIK MicroBooNE NoWire benzeri ornek (test amacli)"
        for name, t in T.items():
            t.write(f, name)
    print(f"Yazıldı: {args.out}  ({args.events} olay)")


if __name__ == "__main__":
    main()
