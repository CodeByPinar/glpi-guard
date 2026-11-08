"""Draws the README artwork into docs/assets/ (pure SVG, no external fonts or images).

    python tools/make-readme-assets.py [cli-output.txt]

logo.svg, hero.svg, social.svg (1280x640, render to social-preview.png for GitHub's social preview)
and terminal.svg (from a real `glpi-guard scan` text output, if given).
"""
import os
import re
import sys
from xml.sax.saxutils import escape

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
OUT = os.path.join(ROOT, 'docs', 'assets')
os.makedirs(OUT, exist_ok=True)

SANS = "'Segoe UI', 'Inter', -apple-system, 'Helvetica Neue', Arial, sans-serif"
MONO = "'JetBrains Mono', 'Cascadia Code', Consolas, 'SFMono-Regular', Menlo, monospace"
SEV = {'Critical': '#F43F5E', 'High': '#FB923C', 'Medium': '#FBBF24', 'Low': '#38BDF8', 'Info': '#94A3B8'}


def shield(x, y, s, gid='sh'):
    """Shield with a check mark, scaled to width s (height 1.18 s)."""
    k = s / 100
    return f'''<g transform="translate({x} {y}) scale({k})">
  <defs>
    <linearGradient id="{gid}" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#34D399"/><stop offset="1" stop-color="#06B6D4"/></linearGradient>
    <linearGradient id="{gid}i" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#0B1220"/><stop offset="1" stop-color="#111C33"/></linearGradient>
  </defs>
  <path d="M50 2 L94 18 C94 60 78 98 50 116 C22 98 6 60 6 18 Z" fill="url(#{gid})"/>
  <path d="M50 13 L84 25.5 C83.5 60 70 89.5 50 104 C30 89.5 16.5 60 16 25.5 Z" fill="url(#{gid}i)"/>
  <path d="M32 58 L45 71 L70 44" fill="none" stroke="url(#{gid})" stroke-width="9" stroke-linecap="round" stroke-linejoin="round"/>
</g>'''


def write(name, svg):
    with open(os.path.join(OUT, name), 'w', encoding='utf-8', newline='\n') as f:
        f.write(svg)
    print('docs/assets/' + name)


def bg(w, h, rx):
    return f'''<defs>
  <linearGradient id="bg" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#070B16"/><stop offset=".55" stop-color="#0E1530"/><stop offset="1" stop-color="#1B1446"/></linearGradient>
  <radialGradient id="glow1" cx=".15" cy=".1" r=".6"><stop offset="0" stop-color="#10B981" stop-opacity=".28"/><stop offset="1" stop-color="#10B981" stop-opacity="0"/></radialGradient>
  <radialGradient id="glow2" cx=".9" cy=".95" r=".55"><stop offset="0" stop-color="#6366F1" stop-opacity=".35"/><stop offset="1" stop-color="#6366F1" stop-opacity="0"/></radialGradient>
  <pattern id="grid" width="32" height="32" patternUnits="userSpaceOnUse"><path d="M32 0H0V32" fill="none" stroke="#FFFFFF" stroke-opacity=".045" stroke-width="1"/></pattern>
  <clipPath id="round"><rect width="{w}" height="{h}" rx="{rx}"/></clipPath>
</defs>
<g clip-path="url(#round)">
  <rect width="{w}" height="{h}" fill="url(#bg)"/>
  <rect width="{w}" height="{h}" fill="url(#grid)"/>
  <rect width="{w}" height="{h}" fill="url(#glow1)"/>
  <rect width="{w}" height="{h}" fill="url(#glow2)"/>
</g>'''


def chip(x, y, text, color='#CBD5E1', fill='#FFFFFF', op='.07'):
    w = 16 + len(text) * 7.6
    return (f'<g transform="translate({x} {y})"><rect width="{w:.0f}" height="30" rx="15" fill="{fill}" fill-opacity="{op}" stroke="#FFFFFF" stroke-opacity=".14"/>'
            f'<text x="{w / 2:.0f}" y="20" text-anchor="middle" font-family="{SANS}" font-size="13.5" font-weight="600" fill="{color}">{escape(text)}</text></g>'), w


def report_card(x, y, w=400):
    rows = [
        ('Critical', 'Default "glpi" account uses its default password', 'GG-B01'),
        ('High', 'GLPI 11.0.8 is affected by 21 security advisories', 'GG-A01'),
        ('High', 'Secret files readable by every local user', 'GG-D02'),
        ('Medium', 'Password security policy is disabled', 'GG-B03'),
        ('Low', 'Active content (HTML, SVG) can be uploaded', 'GG-F02'),
    ]
    out = [f'<g transform="translate({x} {y})">',
           f'<rect width="{w}" height="{112 + len(rows) * 36 + 50}" rx="18" fill="#0B1222" fill-opacity=".88" stroke="#FFFFFF" stroke-opacity=".12"/>',
           # header
           '<rect x="20" y="20" width="58" height="58" rx="14" fill="#F43F5E"/>',
           f'<text x="49" y="62" text-anchor="middle" font-family="{SANS}" font-size="34" font-weight="800" fill="#FFFFFF">F</text>',
           f'<text x="94" y="42" font-family="{SANS}" font-size="13" fill="#94A3B8">Security score</text>',
           f'<text x="94" y="70" font-family="{SANS}" font-size="26" font-weight="700" fill="#F8FAFC">3<tspan fill="#64748B" font-size="18">/100</tspan></text>',
           f'<text x="{w - 20}" y="42" text-anchor="end" font-family="{MONO}" font-size="11.5" fill="#64748B">GLPI 11.0.8</text>',
           f'<rect x="{w - 132}" y="54" width="112" height="24" rx="12" fill="#10B981" fill-opacity=".14" stroke="#10B981" stroke-opacity=".5"/>',
           f'<text x="{w - 76}" y="70" text-anchor="middle" font-family="{SANS}" font-size="11.5" font-weight="600" fill="#6EE7B7">read-only scan</text>',
           f'<line x1="20" y1="96" x2="{w - 20}" y2="96" stroke="#FFFFFF" stroke-opacity=".08"/>']
    yy = 112
    for sev, title, cid in rows:
        c = SEV[sev]
        out.append(f'<rect x="20" y="{yy}" width="4" height="30" rx="2" fill="{c}"/>')
        out.append(f'<rect x="34" y="{yy + 6}" width="{12 + len(sev) * 6.6:.0f}" height="18" rx="9" fill="{c}" fill-opacity=".16"/>')
        out.append(f'<text x="{40 + len(sev) * 3.3:.0f}" y="{yy + 19}" text-anchor="middle" font-family="{SANS}" font-size="10.5" font-weight="700" fill="{c}">{sev.upper()}</text>')
        tx = 34 + 12 + len(sev) * 6.6 + 10
        out.append(f'<text x="{tx:.0f}" y="{yy + 19}" font-family="{SANS}" font-size="12.5" fill="#E2E8F0">{escape(title)}</text>')
        yy += 36
    out.append(f'<rect x="20" y="{yy + 4}" width="{w - 40}" height="26" rx="8" fill="#10B981" fill-opacity=".10"/>')
    out.append(f'<text x="32" y="{yy + 21}" font-family="{SANS}" font-size="12" fill="#A7F3D0">→ every finding comes with evidence and a fix</text>')
    out.append('</g>')
    return '\n'.join(out)


# ---------------------------------------------------------------- logo
write('logo.svg', f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 118" width="100" height="118" role="img" aria-label="GLPI Guard">{shield(0, 0, 100, "lg")}</svg>\n')

# ---------------------------------------------------------------- hero (1280 x 440)
W, H = 1280, 440
parts = [f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {W} {H}" width="{W}" height="{H}" role="img" aria-label="GLPI Guard — read-only security audit for your own GLPI">', bg(W, H, 28)]
parts.append(shield(64, 64, 70, 'hs'))
parts.append(f'<text x="152" y="118" font-family="{SANS}" font-size="58" font-weight="800" fill="#F8FAFC" letter-spacing="-1">GLPI Guard</text>')
parts.append(f'<text x="66" y="194" font-family="{SANS}" font-size="27" font-weight="600" fill="#E2E8F0">Read-only security audit for your own GLPI.</text>')
parts.append(f'<text x="66" y="232" font-family="{SANS}" font-size="17" fill="#94A3B8">Known vulnerabilities, default accounts, risky settings, exposed files —</text>')
parts.append(f'<text x="66" y="258" font-family="{SANS}" font-size="17" fill="#94A3B8">found in seconds, each with the exact fix. Plugin or one-file CLI.</text>')
cx = 66
for t, col in [('25 checks', '#6EE7B7'), ('GLPI 10 · 11 · 12', '#CBD5E1'), ('Plugin + CLI', '#CBD5E1'), ('English · Türkçe', '#CBD5E1'), ('GPL-3.0', '#CBD5E1')]:
    s, w = chip(cx, 300, t, col)
    parts.append(s)
    cx += w + 10
parts.append(f'<text x="66" y="384" font-family="{MONO}" font-size="14" fill="#64748B"><tspan fill="#34D399">$</tspan> php glpi-guard.phar scan /var/www/glpi --format html</text>')
parts.append(report_card(816, 64))
parts.append('</svg>\n')
write('hero.svg', '\n'.join(parts))

# ---------------------------------------------------------------- social preview (1280 x 640)
W, H = 1280, 640
parts = [f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {W} {H}" width="{W}" height="{H}">', bg(W, H, 0)]
parts.append(shield(72, 92, 92, 'ss'))
parts.append(f'<text x="186" y="168" font-family="{SANS}" font-size="72" font-weight="800" fill="#F8FAFC" letter-spacing="-1.5">GLPI Guard</text>')
parts.append(f'<text x="76" y="270" font-family="{SANS}" font-size="34" font-weight="600" fill="#E2E8F0">Read-only security audit</text>')
parts.append(f'<text x="76" y="314" font-family="{SANS}" font-size="34" font-weight="600" fill="#E2E8F0">for your own GLPI.</text>')
parts.append(f'<text x="76" y="372" font-family="{SANS}" font-size="20" fill="#94A3B8">Vulnerabilities · default accounts · risky settings</text>')
parts.append(f'<text x="76" y="402" font-family="{SANS}" font-size="20" fill="#94A3B8">exposed files — with a fix for every finding.</text>')
cx = 76
for t, col in [('25 checks', '#6EE7B7'), ('GLPI 10 · 11 · 12', '#CBD5E1'), ('Plugin + CLI', '#CBD5E1')]:
    s, w = chip(cx, 452, t, col)
    parts.append(s)
    cx += w + 10
parts.append(f'<text x="76" y="560" font-family="{MONO}" font-size="16" fill="#64748B">github.com/CodeByPinar/glpi-guard</text>')
parts.append(report_card(810, 150, 420))
parts.append('</svg>\n')
write('social.svg', '\n'.join(parts))

# ---------------------------------------------------------------- terminal (from real CLI output)
if len(sys.argv) > 1:
    raw = open(sys.argv[1], encoding='utf-8').read().splitlines()
    raw = [re.sub(r'/home/glpi/\w+/glpi', '/var/www/glpi', l) for l in raw]
    keep = raw[:34]
    LH, PADX, TOP = 21, 26, 66
    W = 980
    H = TOP + LH * len(keep) + 28
    parts = [f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {W} {H}" width="{W}" height="{H}" role="img" aria-label="glpi-guard scan output">',
             f'<rect width="{W}" height="{H}" rx="14" fill="#0B1020"/>',
             f'<rect width="{W}" height="40" rx="14" fill="#151C2F"/><rect y="26" width="{W}" height="14" fill="#151C2F"/>',
             '<circle cx="24" cy="20" r="6" fill="#F43F5E"/><circle cx="44" cy="20" r="6" fill="#FBBF24"/><circle cx="64" cy="20" r="6" fill="#34D399"/>',
             f'<text x="{W / 2}" y="25" text-anchor="middle" font-family="{MONO}" font-size="12.5" fill="#94A3B8">php glpi-guard.phar scan /var/www/glpi</text>']
    y = TOP
    in_fix = False
    for l in keep:
        color = '#CBD5E1'
        weight = '400'
        if l.strip().startswith('→'):
            in_fix = True
        elif not l.startswith('    ') or l.strip().startswith('•'):
            in_fix = False
        m = re.match(r'^\[(Critical|High|Medium|Low|Info)\] (.*?)(\s+GG-\w+)?$', l)
        if m:
            sev = m.group(1)
            parts.append(f'<text x="{PADX}" y="{y}" font-family="{MONO}" font-size="13.5" xml:space="preserve"><tspan fill="{SEV[sev]}" font-weight="700">[{sev}]</tspan>'
                         f'<tspan fill="#F8FAFC" font-weight="600"> {escape(m.group(2))}</tspan><tspan fill="#475569">{escape(m.group(3) or "")}</tspan></text>')
            y += LH
            continue
        if l.startswith('Grade'):
            g = re.match(r'^Grade\s+(\w)\s+Score (\d+)/100\s+(.*)$', l)
            if g:
                parts.append(f'<text x="{PADX}" y="{y}" font-family="{MONO}" font-size="13.5" xml:space="preserve"><tspan fill="#94A3B8">Grade </tspan>'
                             f'<tspan fill="#FFFFFF" font-weight="800">[ {g.group(1)} ]</tspan><tspan fill="#94A3B8">   Score </tspan><tspan fill="#F8FAFC" font-weight="700">{g.group(2)}/100</tspan>'
                             f'<tspan fill="#FB923C">   {escape(g.group(3))}</tspan></text>')
                y += LH
                continue
        if l.strip().startswith('•'):
            color = '#64748B'
        elif in_fix:
            color = '#6EE7B7'
        elif l.startswith('glpi-guard'):
            color, weight = '#F8FAFC', '700'
        elif set(l.strip()) == {'─'}:
            color = '#334155'
        parts.append(f'<text x="{PADX}" y="{y}" font-family="{MONO}" font-size="13.5" font-weight="{weight}" fill="{color}" xml:space="preserve">{escape(l)}</text>')
        y += LH
    parts.append('</svg>\n')
    write('terminal.svg', '\n'.join(parts))


# ---------------------------------------------------------------- badges & navigation (docs/assets/ui/)
UI = os.path.join(OUT, 'ui')
os.makedirs(UI, exist_ok=True)


def text_w(s, size):
    """rough text width for the sans stack (no font metrics available in plain SVG)"""
    w = 0.0
    for ch in s:
        if ch in 'il.,:;|!\'1 ·':
            w += 0.30
        elif ch in 'fjrtI()[]':
            w += 0.38
        elif ch in 'mwMW':
            w += 0.86
        elif ch.isupper() or ch in '+#%&@':
            w += 0.66
        else:
            w += 0.55
    return w * size


ICONS = {  # 16x16 stroke icons
    'layers': '<path d="M8 2 1.5 5.5 8 9l6.5-3.5Z"/><path d="m1.5 8.5 6.5 3.5 6.5-3.5"/><path d="m1.5 11.5 6.5 3.5 6.5-3.5"/>',
    'code': '<path d="m5.5 4-4 4 4 4"/><path d="m10.5 4 4 4-4 4"/><path d="m9 2.5-2 11"/>',
    'check': '<circle cx="8" cy="8" r="6.5"/><path d="m5 8.2 2 2 4-4.2"/>',
    'plug': '<path d="M6 1.5v3M10 1.5v3"/><path d="M3.5 4.5h9v2.5a4.5 4.5 0 0 1-9 0Z"/><path d="M8 11.5v3"/>',
    'globe': '<circle cx="8" cy="8" r="6.5"/><path d="M1.5 8h13"/><path d="M8 1.5c2 2 2.8 4.2 2.8 6.5S10 12.5 8 14.5C6 12.5 5.2 10.3 5.2 8S6 3.5 8 1.5Z"/>',
    'scale': '<path d="M8 2v12M4 14h8"/><path d="M3 4.5h10"/><path d="m3 4.5-2 4.5h4Z"/><path d="m13 4.5-2 4.5h4Z"/>',
    'eye': '<path d="M1.5 8S4 3.5 8 3.5 14.5 8 14.5 8 12 12.5 8 12.5 1.5 8 1.5 8Z"/><circle cx="8" cy="8" r="2"/>',
    'shield': '<path d="M8 1.5 13.5 3.5c0 5-2 8.6-5.5 11-3.5-2.4-5.5-6-5.5-11Z"/><path d="m5.6 8 1.7 1.7 3.2-3.4"/>',
}


def badge(name, icon, label, value, color):
    fs = 11.5
    lw, vw = text_w(label, fs) * 1.08, text_w(value, fs) * 1.18
    x_l = 30
    x_v = x_l + lw + 11
    w = x_v + vw + 9 + 6
    svg = f'''<svg xmlns="http://www.w3.org/2000/svg" width="{w:.0f}" height="26" viewBox="0 0 {w:.0f} 26" role="img" aria-label="{escape(label)}: {escape(value)}">
<defs><linearGradient id="b" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#141C33"/><stop offset="1" stop-color="#0B1222"/></linearGradient></defs>
<rect x=".5" y=".5" width="{w - 1:.0f}" height="25" rx="12.5" fill="url(#b)" stroke="#2A3550"/>
<rect x="{x_v - 7:.1f}" y="3.5" width="{vw + 14:.1f}" height="19" rx="9.5" fill="{color}" fill-opacity=".15"/>
<g transform="translate(10 6) scale(.875)" fill="none" stroke="{color}" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">{ICONS[icon]}</g>
<text x="{x_l}" y="17" font-family="{SANS}" font-size="{fs}" font-weight="600" fill="#94A3B8">{escape(label)}</text>
<text x="{x_v:.1f}" y="17" font-family="{SANS}" font-size="{fs}" font-weight="700" fill="{color}">{escape(value)}</text>
</svg>
'''
    with open(os.path.join(UI, name + '.svg'), 'w', encoding='utf-8', newline='\n') as f:
        f.write(svg)
    print('docs/assets/ui/' + name + '.svg')


for args in [
    ('badge-glpi', 'layers', 'GLPI', '10 · 11 · 12', '#60A5FA'),
    ('badge-php', 'code', 'PHP', '7.4+', '#A5B4FC'),
    ('badge-checks', 'check', 'checks', '25', '#34D399'),
    ('badge-mode', 'plug', 'runs as', 'plugin + CLI', '#22D3EE'),
    ('badge-lang', 'globe', 'lang', 'EN · TR', '#FBBF24'),
]:
    badge(*args)


def nav(name, label, primary=False):
    fs = 13.5
    tw = text_w(label, fs)
    w = tw + (40 if primary else 32)
    if primary:
        body = (f'<defs><linearGradient id="p" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#34D399"/><stop offset="1" stop-color="#06B6D4"/></linearGradient></defs>'
                f'<rect width="{w:.0f}" height="36" rx="18" fill="url(#p)"/>'
                f'<text x="{w / 2:.1f}" y="23" text-anchor="middle" font-family="{SANS}" font-size="{fs}" font-weight="700" fill="#04111F">{escape(label)}</text>')
    else:
        body = (f'<rect x=".5" y=".5" width="{w - 1:.0f}" height="35" rx="17.5" fill="#0F172A" stroke="#2A3550"/>'
                f'<text x="{w / 2:.1f}" y="23" text-anchor="middle" font-family="{SANS}" font-size="{fs}" font-weight="600" fill="#E2E8F0">{escape(label)}</text>')
    svg = f'<svg xmlns="http://www.w3.org/2000/svg" width="{w:.0f}" height="36" viewBox="0 0 {w:.0f} 36" role="img" aria-label="{escape(label)}">{body}</svg>\n'
    with open(os.path.join(UI, name + '.svg'), 'w', encoding='utf-8', newline='\n') as f:
        f.write(svg)
    print('docs/assets/ui/' + name + '.svg')


for n, l, p in [('nav-quickstart', 'Quick start  →', True), ('nav-checks', 'Checks', False), ('nav-screenshots', 'Screenshots', False),
                ('nav-how', 'How it works', False), ('nav-cli', 'CLI', False), ('nav-faq', 'FAQ', False), ('nav-roadmap', 'Roadmap', False),
                ('nav-tr', 'Türkçe', False)]:
    nav(n, l, p)
