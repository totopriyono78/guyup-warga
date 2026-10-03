"""Logo Rukoon: dua cincin bertaut (warga yang rukun) di bawah satu atap."""
import math, os
from fontTools.ttLib import TTFont
from fontTools.pens.svgPathPen import SVGPathPen
from fontTools.pens.boundsPen import BoundsPen

D = os.path.dirname(os.path.abspath(__file__))
OUT = os.path.join(D, 'out'); os.makedirs(OUT, exist_ok=True)
FONT = TTFont(os.path.join(D, 'package/files/plus-jakarta-sans-latin-800-normal.woff2'))
GS = FONT.getGlyphSet(); CMAP = FONT.getBestCmap(); UPM = FONT['head'].unitsPerEm

TEAL_A, TEAL_B = '#14b8a6', '#0f766e'   # gradasi tile
TEAL_D = '#115e59'
AMBER = '#fbbf24'
INK = '#0f172a'


def arc(cx, cy, r, a0, a1):
    p = lambda a: (cx + r * math.cos(math.radians(a)), cy + r * math.sin(math.radians(a)))
    (x0, y0), (x1, y1) = p(a0), p(a1)
    return f'M{x0:.2f} {y0:.2f}A{r} {r} 0 0 1 {x1:.2f} {y1:.2f}'


def cincin(cx1, cy, r, jarak, sw, w1, w2):
    """Dua cincin bertaut: cincin kiri (w1) di atas cincin kanan (w2) pada titik potong atas,
    dan di bawahnya pada titik potong bawah — seperti mata rantai."""
    cx2 = cx1 + jarak
    h = math.sqrt(r * r - (jarak / 2) ** 2)
    sudut = math.degrees(math.atan2(h, jarak / 2))      # sudut titik potong dari pusat kiri
    s = []
    s.append(f'<circle cx="{cx1}" cy="{cy}" r="{r}" fill="none" stroke="{w1}" stroke-width="{sw}"/>')
    s.append(f'<circle cx="{cx2}" cy="{cy}" r="{r}" fill="none" stroke="{w2}" stroke-width="{sw}"/>')
    # potongan cincin kiri digambar ulang di atas titik potong ATAS -> bertaut
    s.append(f'<path d="{arc(cx1, cy, r, -sudut - 22, -sudut + 22)}" fill="none" stroke="{w1}" stroke-width="{sw}" stroke-linecap="butt"/>')
    return '\n'.join(s)


def tile(ukuran=64, rx=16, id_='g'):
    return (f'<defs><linearGradient id="{id_}" x1="0" y1="0" x2="1" y2="1">'
            f'<stop offset="0" stop-color="{TEAL_A}"/><stop offset="1" stop-color="{TEAL_B}"/></linearGradient></defs>'
            f'<rect width="{ukuran}" height="{ukuran}" rx="{rx}" fill="url(#{id_})"/>')


def isi_tanda(w_atap='#fff', w1='#fff', w2=AMBER, sw=4.6):
    """Isi tanda dalam kotak 64x64: atap + dua cincin."""
    atap = f'<path d="M13.5 28.5 32 14.5l18.5 14" fill="none" stroke="{w_atap}" stroke-width="{sw}" stroke-linecap="round" stroke-linejoin="round"/>'
    return atap + cincin(25, 40, 9, 14, sw, w1, w2)


def svg(w, h, isi, vb=None):
    vb = vb or f'0 0 {w} {h}'
    return f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="{vb}" width="{w}" height="{h}">{isi}</svg>\n'


# ---------- 1. Tanda (ikon aplikasi / favicon) ----------
tanda = svg(64, 64, tile() + isi_tanda())
open(f'{OUT}/logo.svg', 'w').write(tanda)

# versi "maskable" (Android) — ruang aman 80%
mask = svg(512, 512, f'<rect width="512" height="512" fill="{TEAL_B}"/>'
           f'<defs><linearGradient id="m" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="{TEAL_A}"/><stop offset="1" stop-color="{TEAL_B}"/></linearGradient></defs>'
           f'<rect width="512" height="512" fill="url(#m)"/>'
           f'<g transform="translate(76.8 76.8) scale(5.6)">{isi_tanda()}</g>')
open(f'{OUT}/maskable.svg', 'w').write(mask)


# ---------- 2. Wordmark "rukoon" dengan "oo" = dua cincin ----------
def glyph_path(ch, x, skala, baseline):
    g = CMAP[ord(ch)]; pen = SVGPathPen(GS)
    GS[g].draw(pen)
    adv = GS[g].width
    return f'<path transform="translate({x:.2f} {baseline}) scale({skala} {-skala})" d="{pen.getCommands()}"/>', adv * skala


def wordmark(warna_teks, w1, w2, tinggi_x_px=40):
    xh = FONT['OS/2'].sxHeight
    sk = tinggi_x_px / xh
    baseline = 70
    parts, x = [], 0.0
    for ch in 'ruk':
        p, adv = glyph_path(ch, x, sk, baseline); parts.append(p); x += adv
    # dua cincin seukuran huruf "o"
    bp = BoundsPen(GS); GS[CMAP[ord('o')]].draw(bp)
    xmin, ymin, xmax, ymax = bp.bounds
    o_w = (xmax - xmin) * sk
    sw = tinggi_x_px * 0.19
    r = (o_w - sw) / 2 * 1.0
    jarak = r * 1.55
    cx1 = x + xmin * sk + r + sw / 2 + 1.5
    cy = baseline - tinggi_x_px / 2
    ring = cincin(round(cx1, 2), round(cy, 2), round(r, 2), round(jarak, 2), round(sw, 2), w1, w2)
    x = cx1 + jarak + r + sw / 2 + (GS[CMAP[ord('o')]].width - xmax) * sk + 1.5
    p, adv = glyph_path('n', x, sk, baseline); parts.append(p); x += adv
    return f'<g fill="{warna_teks}">{"".join(parts)}</g>{ring}', x, baseline


wm, lebar, base = wordmark(INK, TEAL_B, AMBER)
# logo horizontal: tanda + wordmark
H = 96
logo_h = svg(round(96 + 18 + lebar + 6), H,
             f'<g transform="translate(4 8) scale(1.25)">{tile(id_="h")}{isi_tanda()}</g>'
             f'<g transform="translate({4 + 80 + 18} {-base + 66})">{wm}</g>')
open(f'{OUT}/rukoon.svg', 'w').write(logo_h)

wm_p, _, _ = wordmark('#ffffff', '#ffffff', AMBER)
logo_putih = svg(round(96 + 18 + lebar + 6), H,
                 f'<g transform="translate(4 8) scale(1.25)"><rect width="64" height="64" rx="16" fill="#ffffff" fill-opacity=".14"/>{isi_tanda()}</g>'
                 f'<g transform="translate({4 + 80 + 18} {-base + 66})">{wm_p}</g>')
open(f'{OUT}/rukoon-putih.svg', 'w').write(logo_putih)

wm_only = svg(round(lebar + 4), 90, f'<g transform="translate(2 {-base + 66})">{wm}</g>')
open(f'{OUT}/wordmark.svg', 'w').write(wm_only)
print('ok', lebar)

# ---------- 3. Gambar pratinjau tautan (WhatsApp / media sosial) ----------
wm_og, lebar_og, base_og = wordmark('#ffffff', '#ffffff', AMBER, tinggi_x_px=64)
og = svg(1200, 630,
    f'<defs><linearGradient id="o" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="{TEAL_A}"/><stop offset="1" stop-color="{TEAL_D}"/></linearGradient></defs>'
    f'<rect width="1200" height="630" fill="url(#o)"/>'
    f'<circle cx="1080" cy="80" r="260" fill="#ffffff" fill-opacity=".06"/><circle cx="120" cy="600" r="200" fill="#ffffff" fill-opacity=".05"/>'
    f'<g transform="translate({600 - (150 + 28 + lebar_og) / 2:.1f} 190) scale(2.35)"><rect width="64" height="64" rx="16" fill="#ffffff" fill-opacity=".16"/>{isi_tanda()}</g>'
    f'<g transform="translate({600 - (150 + 28 + lebar_og) / 2 + 150 + 28:.1f} {-base_og + 300})">{wm_og}</g>'
    f'<text x="600" y="470" text-anchor="middle" font-family="Plus Jakarta Sans, DejaVu Sans, sans-serif" font-size="34" font-weight="600" fill="#ccfbf1">Aplikasi warga RT/RW: rukun, rapi, terbuka</text>')
open(f'{OUT}/og.svg', 'w').write(og)
