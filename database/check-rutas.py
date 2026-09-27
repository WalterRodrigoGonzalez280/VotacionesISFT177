#!/usr/bin/env python3
"""
Verifica que los includes de www/ resuelvan y que las rutas de la interfaz
existan. Corre antes de cada commit: asi se cazan los `__DIR__ . '...'` sin
barra inicial, que en PHP producen `/var/www/htmlincludes/...` y tiran un
Warning en el <head> de cada pagina.

    python3 database/check-rutas.py

Sale con codigo 1 si algo falla.
"""
import os
import re
import sys
import pathlib

RAIZ = pathlib.Path(__file__).resolve().parent.parent
WWW = RAIZ / 'www'
fallos = []


def norm(base_dir, ruta):
    """Resuelve una ruta con .NET, como hace PHP con __DIR__ . '...'."""
    return os.path.normpath(os.path.join(str(base_dir), ruta.lstrip('/')))


print('=' * 68)
print('INCLUDES DE PHP')
print('=' * 68)

for php in sorted(WWW.rglob('*.php')):
    texto = php.read_text(encoding='utf-8')
    patron = r"(?:include|require)(?:_once)?\s+__DIR__\s*\.\s*'([^']+)'"
    for m in re.finditer(patron, texto):
        destino = norm(php.parent, m.group(1))
        ok = os.path.exists(destino)
        rel = php.relative_to(RAIZ)
        print('  {} {:28} {}'.format('ok  ' if ok else 'ROTO', f'{rel}: {m.group(1)}', '' if ok else f'-> {destino} no existe'))
        if not ok:
            fallos.append(f'{rel}: include {m.group(1)}')

print()
print('=' * 68)
print('RECURSOS QUE REFERENCIAN LAS PAGINAS')
print('=' * 68)

referencias = re.compile(r'(?:href|src)="((?:\.\./)?(?:css|js|Logo)/[^"]+)"')
vistos = set()

for php in sorted(WWW.rglob('*.php')):
    texto = php.read_text(encoding='utf-8')
    for m in referencias.finditer(texto):
        # El navegador resuelve un href relativo contra la URL de la pagina
        # (ej: login/login.php + ../css/x.css -> /css/x.css), o sea contra el
        # directorio del archivo. Prefijar el ../ otra vez, o resolver desde
        # www/, daba falso positivo en todo lo que vive en un subdirectorio.
        destino = norm(php.parent, m.group(1))
        vistos.add(destino)
        if not os.path.exists(destino):
            print('  ROTO {} -> {}'.format(php.relative_to(RAIZ), m.group(1)))
            fallos.append(f'{php.relative_to(RAIZ)}: falta {m.group(1)}')

print('  {} recursos referenciados, todos existen'.format(len(vistos)) if not fallos else '')

print()
print('=' * 68)
print('HIERARCHIA DE ENCABEZADOS')
print('=' * 68)

for php in sorted(WWW.rglob('*.php')):
    # includes/ son parciales, no paginas: no se les exige un unico h1.
    if 'includes' in php.parts:
        continue
    texto = php.read_text(encoding='utf-8')
    niveles = [int(n) for n in re.findall(r'<h([1-6])[\s>]', texto)]
    if not niveles:
        continue
    saltos = [(niveles[i - 1], niveles[i]) for i in range(1, len(niveles)) if niveles[i] > niveles[i - 1] + 1]
    h1 = niveles.count(1)
    mal = h1 != 1 or saltos
    print('  {} {:24} h1={} {}'.format('MAL ' if mal else 'ok  ', php.name, h1, saltos or ''))
    if mal:
        fallos.append(f'{php.relative_to(RAIZ)}: h1={h1} saltos={saltos}')

print()
print('=' * 68)
print('NAVEGACION REAL (solo si el server esta arriba)')
print('=' * 68)

# El chequeo estatico NO puede detectar este bug: un href que vive en un
# parcial (includes/) lo resuelve el navegador contra la URL de la PAGINA, no
# contra la del parcial, y check-rutas lo resuelve contra el directorio del
# archivo. Sin sesion, /login/login.php + "index.php" = /login/index.php = 404,
# que el chequeo estatico da por bueno. Hace falta preguntarle al server.

BASE = os.environ.get('BASE_URL', 'http://localhost:4200')

try:
    import urllib.error
    import urllib.request

    def status(url):
        req = urllib.request.Request(url, headers={'Accept': 'text/html'})
        req.get_method = lambda: 'GET'
        try:
            with urllib.request.urlopen(req, timeout=5) as r:
                return r.status
        except urllib.error.HTTPError as e:
            return e.code
        except Exception:
            return 0

    raiz = BASE.rstrip('/')
    # Se recorren los links desde CADA pagina auth, que es donde rompen:
    # las de raiz funcionan por casualidad y no las del subdirectorio.
    paginas = ['/login/login.php', '/login/recuperar.php',
               '/registro/registro.php', '/registro/activar.php']
    for pagina in paginas:
        try:
            with urllib.request.urlopen(f'{raiz}{pagina}', timeout=5) as r:
                html = r.read().decode('utf-8', 'replace')
        except Exception as e:
            print('  -- no se pudo leer {}: {}'.format(pagina, e))
            continue

        # Hay que tomar los href RELATIVOS tambien. Con el regex anterior
        # (solo los que empiezan con /) un link relativo se escapaba del
        # chequeo, que es justo el bug que se quiere cazar.
        #
        # urljoin es el algoritmo de resolucion del navegador: resuelve
        # "index.php" contra la URL de la pagina, o sea /login/index.php.
        import urllib.parse

        base_url = raiz + pagina
        hrefs = sorted({
            m for m in re.findall(r'href="([^"#?]*)"', html)
            if m and not m.startswith(('http', 'mailto:', 'data:', 'javascript:'))
        })
        for h in hrefs:
            absoluto = urllib.parse.urljoin(base_url, h)
            h = absoluto[len(raiz):] if absoluto.startswith(raiz) else absoluto
            code = status(raiz + h)
            # 302 es correcto: la pagina exige sesion y redirige.
            ok = code in (200, 302)
            if not ok:
                fallos.append(f'{pagina}: {h} -> HTTP {code}')
            print('  {} {:24} -> {} {}'.format(
                'ok  ' if ok else 'ROTO', pagina + h, code, '' if ok else '<<<'))

except ImportError:
    print('  (urllib no disponible)')

print()
print('=' * 68)
if fallos:
    print('{} PROBLEMAS:'.format(len(fallos)))
    for f in fallos:
        print('  -', f)
    sys.exit(1)

print('TODO OK')
