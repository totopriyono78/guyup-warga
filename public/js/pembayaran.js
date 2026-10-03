/* Rukoon — halaman QRIS: gambar QR, hitung mundur, cek status berkala (iuran & donasi). */
function pembayaran(o) {
    return {
        status: o.status, content: o.content, va: o.va, sisa: '--:--', habis: false, timer: null, poll: null,
        mulai() {
            if (this.status !== 'pending') return;
            this.$nextTick(() => this.gambar());
            this.hitung();
            this.timer = setInterval(() => this.hitung(), 1000);
            this.poll = setInterval(() => this.cek(), 5000);
        },
        gambar() {
            if (this.va || !this.content || !this.$refs.qr || typeof qrcode === 'undefined') return;
            const qr = qrcode(0, 'M');
            qr.addData(this.content);
            qr.make();
            this.$refs.qr.innerHTML = qr.createSvgTag({ cellSize: 8, margin: 0, scalable: true });
        },
        hitung() {
            if (!o.expired) return;
            const d = Math.max(0, Math.floor((new Date(o.expired) - new Date()) / 1000));
            this.sisa = String(Math.floor(d / 60)).padStart(2, '0') + ':' + String(d % 60).padStart(2, '0');
            // beri waktu tambahan 2 menit untuk pembayaran yang sedang diproses
            if (d === 0 && new Date() - new Date(o.expired) > 120000) { this.habis = true; }
        },
        async cek() {
            try {
                const r = await fetch(o.url, { headers: { 'Accept': 'application/json' } });
                if (!r.ok) return;
                const j = await r.json();
                this.status = j.status;
                if (j.status !== 'pending') { clearInterval(this.poll); clearInterval(this.timer); }
            } catch (e) {}
        },
        unduh() {
            const svg = this.$refs.qr.querySelector('svg');
            if (!svg) return;
            const img = new Image(), c = document.createElement('canvas');
            const data = 'data:image/svg+xml;base64,' + btoa(new XMLSerializer().serializeToString(svg));
            img.onload = () => {
                c.width = c.height = 800;
                const ctx = c.getContext('2d');
                ctx.fillStyle = '#fff'; ctx.fillRect(0, 0, 800, 800);
                ctx.drawImage(img, 40, 40, 720, 720);
                const a = document.createElement('a');
                a.download = o.namaFile || 'qris.png'; a.href = c.toDataURL('image/png'); a.click();
            };
            img.src = data;
        },
    }
}
