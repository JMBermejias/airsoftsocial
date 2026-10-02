"""Genera favicon.ico a partir de los iconos PNG de la app.

Por qué existe: cuando se crea un acceso directo (sin instalar la PWA), Chrome y
Android **no usan el manifest**: usan el favicon. Si el favicon no existe o está
corrupto, sale el icono genérico del sistema. Y un .ico mal generado es peor que
no tener ninguno.

El .ico anterior estaba corrupto: declaraba en el catálogo el tamaño de cada
frame sin contar la máscara AND que exige el formato, así que al leerlo los
navegadores se trababan a mitad y lo descartaban.

Uso: python3 scripts/make_favicon.py
"""
import os
from PIL import Image

RAIZ = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
ICONOS = os.path.join(RAIZ, 'assets', 'img', 'icons')
DESTINO = os.path.join(RAIZ, 'favicon.ico')

# De dónde sale cada tamaño del favicon. Se usa el icono de 192 para los
# pequeños y el de 512 para el grande, para que no se vea borroso.
ORIGENES = {
    16:  'icon-192.png',
    32:  'icon-192.png',
    48:  'icon-192.png',
    64:  'icon-192.png',
    128: 'icon-512.png',
    256: 'icon-512.png',
}

print('Generando favicon.ico')

# Pillow solo admite los tamaños estándar de .ico, de 16 a 256, y para meter
# varios frames hay que pasar la imagen más grande como principal y las demás
# en `append_images` con su tamaño declarado en `sizes`.
marcos = []
for size, nombre in sorted(ORIGENES.items()):
    ruta = os.path.join(ICONOS, nombre)
    if not os.path.isfile(ruta):
        raise SystemExit('Falta el icono de origen: %s' % ruta)
    im = Image.open(ruta).convert('RGBA')

    # Encaja el icono centrado en un lienzo cuadrado del tamaño pedido, sin
    # deformarlo: el favicon no se recorta, así que se respeta la proporción.
    marco = Image.new('RGBA', (size, size), (0, 0, 0, 0))
    escala = min(size / im.width, size / im.height)
    nw, nh = max(1, round(im.width * escala)), max(1, round(im.height * escala))
    marco.paste(im.resize((nw, nh), Image.LANCZOS),
                ((size - nw) // 2, (size - nh) // 2))
    marcos.append(marco)
    print('  %-4d <- %-16s' % (size, nombre))

# El frame más grande va primero: es el que Pillow usa como base del .ico.
orden = list(reversed(marcos))
orden[0].save(DESTINO, format='ICO',
              sizes=[(m.width, m.height) for m in orden],
              append_images=orden[1:])

# Comprobación: hay que poder releerlo y tener todos los tamaños.
verif = Image.open(DESTINO)
print('\nescrito favicon.ico (%d bytes)' % os.path.getsize(DESTINO))
print('tamaños dentro del .ico:', sorted(verif.info.get('sizes', [])))
print('tamaño principal:', verif.size)

# Releer cada frame uno a uno: si alguno falla, el .ico está corrupto.
fallos = 0
for s in sorted(ORIGENES):
    try:
        f = Image.open(DESTINO)
        f.size = (s, s)
        f.load()
        print('  frame %dx%d: se lee bien' % (s, s))
    except Exception as e:
        print('  frame %dx%d: FALLO %s' % (s, s, e))
        fallos += 1

raise SystemExit(1 if fallos else 0)