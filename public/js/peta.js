/* Rukoon — utilitas peta Leaflet (dipakai halaman Denah & editor titik rumah). */
(function () {
    const WARNA = {
        terisi: '#10b981', kontrakan: '#0ea5e9', belum_didata: '#ffffff', kosong: '#cbd5e1', usaha: '#f59e0b',
        lunas: '#10b981', sebagian: '#f59e0b', belum: '#f43f5e', tidak_ada: '#cbd5e1',
    };
    const TEKS_GELAP = ['belum_didata', 'kosong', 'usaha', 'sebagian', 'tidak_ada'];

    function esc(s) {
        return String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    }

    async function lapisanGoogle(key) {
        try {
            const r = await fetch('https://tile.googleapis.com/v1/createSession?key=' + encodeURIComponent(key), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ mapType: 'satellite', language: 'id-ID', region: 'ID', layerTypes: ['layerRoadmap'] }),
            });
            if (!r.ok) throw new Error(await r.text());
            const s = await r.json();
            return L.tileLayer(
                'https://tile.googleapis.com/v1/2dtiles/{z}/{x}/{y}?session=' + encodeURIComponent(s.session) + '&key=' + encodeURIComponent(key),
                { maxNativeZoom: 20, maxZoom: 21, attribution: 'Citra &copy; Google' },
            );
        } catch (e) {
            console.warn('[Rukoon] Google Map Tiles gagal dimuat, memakai citra Esri.', e);
            return null;
        }
    }

    // Objek Leaflet tidak boleh dibungkus reaktivitas Alpine (Proxy). Bila terbungkus, pendengar event
    // gagal dilepas saat marker diganti, sehingga animasi zoom error dan titik tidak ikut bergerak.
    // Penanda __v_skip dihormati oleh @vue/reactivity yang dipakai Alpine.
    if (window.L) {
        L.Map.prototype.__v_skip = true;
        L.Layer.prototype.__v_skip = true;
        if (L.Control) L.Control.prototype.__v_skip = true;
    }

    window.SiwargaPeta = {
        WARNA,
        esc,

        /** Membuat peta dengan lapisan Satelit (Google/Esri) & Peta jalan (OSM). */
        async buat(el, cfg, opsi = {}) {
            const awal = (cfg && cfg.awal) || { lat: -2.5, lng: 118, zoom: 5 };
            const map = L.map(el, { maxZoom: 21, zoomControl: true }).setView([awal.lat, awal.lng], awal.zoom);

            const jalan = L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxNativeZoom: 19, maxZoom: 21, attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
            });

            let satelit = cfg && cfg.googleKey ? await lapisanGoogle(cfg.googleKey) : null;
            if (!satelit) {
                satelit = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
                    maxNativeZoom: 19, maxZoom: 21, attribution: 'Citra &copy; Esri, Maxar, Earthstar Geographics',
                });
            }

            (opsi.dasar === 'jalan' ? jalan : satelit).addTo(map);
            L.control.layers({ 'Satelit': satelit, 'Peta jalan': jalan }, {}, { position: 'topright' }).addTo(map);
            L.control.scale({ imperial: false }).addTo(map);

            // Ukuran titik rumah mengikuti zoom (label hanya tampil saat cukup dekat)
            const skala = () => {
                const z = map.getZoom();
                el.classList.toggle('sw-z18', z >= 17.5 && z < 19);
                el.classList.toggle('sw-z17', z < 17.5);
            };
            map.on('zoomend', skala);
            skala();

            // Leaflet perlu dihitung ulang bila kontainer baru tampil (mis. di dalam x-show)
            setTimeout(() => map.invalidateSize(), 150);

            return map;
        },

        /** Ikon titik rumah: kotak kecil berwarna berisi nomor rumah. */
        ikon(kat, label, opsi = {}) {
            const bg = WARNA[kat] || '#64748b';
            const fg = TEKS_GELAP.includes(kat) ? '#0f172a' : '#ffffff';
            const garis = kat === 'belum_didata' ? 'dashed' : 'solid';
            const cincin = opsi.dipilih ? 'box-shadow:0 0 0 4px #facc15;' : '';
            const tepi = opsi.warnaRt ? opsi.warnaRt : 'rgba(15,23,42,.55)';
            return L.divIcon({
                className: 'sw-pin-wrap',
                html: `<div class="sw-pin" style="background:${bg};color:${fg};border:2px ${garis} ${tepi};${cincin}">${esc(label)}</div>`,
                iconSize: [30, 22],
                iconAnchor: [15, 11],
            });
        },

        /** Cari lokasi via Nominatim (OpenStreetMap). */
        async cariLokasi(q) {
            const url = 'https://nominatim.openstreetmap.org/search?format=json&limit=6&countrycodes=id&accept-language=id&q=' + encodeURIComponent(q);
            const r = await fetch(url, { headers: { 'Accept': 'application/json' } });
            if (!r.ok) throw new Error('Pencarian lokasi gagal (' + r.status + ')');
            return (await r.json()).map(x => ({ nama: x.display_name, lat: +x.lat, lng: +x.lon, bbox: x.boundingbox }));
        },

        /** Ambil bangunan dari OpenStreetMap (Overpass API) di dalam batas peta. */
        async bangunanOsm(bounds) {
            const s = bounds.getSouth(), w = bounds.getWest(), n = bounds.getNorth(), e = bounds.getEast();
            const q = `[out:json][timeout:25];(way["building"](${s},${w},${n},${e});relation["building"](${s},${w},${n},${e}););out center tags 2000;`;
            const r = await fetch('https://overpass-api.de/api/interpreter', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'data=' + encodeURIComponent(q),
            });
            if (!r.ok) throw new Error('Server OpenStreetMap sibuk (' + r.status + '). Coba lagi sebentar lagi.');
            const j = await r.json();
            return (j.elements || [])
                .filter(el => el.center)
                .map(el => ({
                    id: el.type + el.id,
                    lat: el.center.lat,
                    lng: el.center.lon,
                    nomor: (el.tags && el.tags['addr:housenumber']) || '',
                    jalan: (el.tags && el.tags['addr:street']) || '',
                }));
        },
    };
})();
