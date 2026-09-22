#!/usr/bin/env python3
"""Refreshes languages/monoranks-<locale>.po from languages/monoranks.pot: keeps every translation, takes the source references
and plural forms from the POT (make-json needs the references to know which strings the admin app uses), and lists the
strings still missing a translation at the end of the run."""
import re, sys, ast, pathlib

root = pathlib.Path(__file__).resolve().parent.parent
pot = (root / 'languages/monoranks.pot').read_text()

def parse(text):
    entries, cur = [], {}
    def flush():
        if cur.get('msgid') is not None:
            entries.append(dict(cur))
        cur.clear()
    for block in re.split(r'\n\n+', text.strip()):
        cur.clear()
        refs, key, buf = [], None, {}
        for line in block.split('\n'):
            if line.startswith('#:'):
                refs.append(line[2:].strip())
            elif line.startswith('#'):
                cur.setdefault('comments', []).append(line)
            else:
                m = re.match(r'^(msgid_plural|msgid|msgstr\[\d\]|msgstr|msgctxt)\s+(".*")$', line)
                if m:
                    key = m.group(1); buf[key] = ast.literal_eval(m.group(2))
                elif line.startswith('"') and key:
                    buf[key] += ast.literal_eval(line)
        if 'msgid' in buf:
            cur.update(buf); cur['refs'] = refs
            entries.append(dict(cur))
    return entries

def q(s):
    return '"' + s.replace('\\', '\\\\').replace('"', '\\"').replace('\n', '\\n') + '"'

template = parse(pot)
for po_path in sorted((root / 'languages').glob('monoranks-*.po')):
    # Keyed by context and id together, so a string that exists in two contexts keeps both translations.
    old = {(e.get('msgctxt', ''), e['msgid']): e for e in parse(po_path.read_text())}
    header = old.get(('', ''), {'msgstr': ''})
    out = ['msgid ""', 'msgstr ' + q(header['msgstr']), '']
    missing = []
    for e in template:
        if e['msgid'] == '':
            continue
        prev = old.get((e.get('msgctxt', ''), e['msgid']), {})
        for c in e.get('comments', []):
            if c.startswith('#.') or c.startswith('#,'):
                out.append(c)
        for r in e['refs']:
            out.append('#: ' + r)
        if e.get('msgctxt'):
            out.append('msgctxt ' + q(e['msgctxt']))
        out.append('msgid ' + q(e['msgid']))
        if 'msgid_plural' in e:
            out.append('msgid_plural ' + q(e['msgid_plural']))
            out.append('msgstr[0] ' + q(prev.get('msgstr[0]', '')))
            out.append('msgstr[1] ' + q(prev.get('msgstr[1]', '')))
            if not prev.get('msgstr[0]'):
                missing.append(e['msgid'])
        else:
            out.append('msgstr ' + q(prev.get('msgstr', '')))
            if not prev.get('msgstr'):
                missing.append(e['msgid'])
        out.append('')
    po_path.write_text('\n'.join(out))
    print(f'{po_path.name}: {len(template) - 1} strings, {len(missing)} untranslated')
    for m in missing:
        print('  ' + m)
