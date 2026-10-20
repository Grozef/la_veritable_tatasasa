/**
 * Chaine de filtres en WebGL.
 *
 * Quatre reglages en apercu temps reel sur une image de plusieurs megapixels : en 2D,
 * chaque mouvement de curseur repasserait sur tous les pixels en JavaScript et l'apercu
 * saccaderait. Ici le GPU refait la passe entiere a chaque image.
 *
 * NON DESTRUCTIF : ce module ne modifie jamais la source. Les reglages sont des nombres
 * ranges a cote de l'image, reappliques a l'export.
 */

export interface Reglages {
  /** En diaphragmes : +1 double la lumiere. */
  exposition: number
  contraste: number
  saturation: number
  /** Positif = plus chaud. Rattrape une lampe d'atelier, pas une balance des blancs ratee. */
  temperature: number
}

export const REGLAGES_NEUTRES: Reglages = {
  exposition: 0,
  contraste: 0,
  saturation: 0,
  temperature: 0,
}

export function reglagesNeutres(reglages: Reglages): boolean {
  return (
    reglages.exposition === 0 &&
    reglages.contraste === 0 &&
    reglages.saturation === 0 &&
    reglages.temperature === 0
  )
}

const SOMMETS = `
attribute vec2 position;
varying vec2 uv;

void main() {
  // L'origine d'une texture WebGL est en BAS a gauche, celle d'une image en HAUT a
  // gauche : sans ce retournement du Y, l'apercu sort a l'envers.
  uv = vec2((position.x + 1.0) / 2.0, (1.0 - position.y) / 2.0);
  gl_Position = vec4(position, 0.0, 1.0);
}
`

const FRAGMENTS = `
precision mediump float;

uniform sampler2D image;
uniform float exposition;
uniform float contraste;
uniform float saturation;
uniform float temperature;

varying vec2 uv;

void main() {
  vec4 pixel = texture2D(image, uv);
  vec3 couleur = pixel.rgb;

  couleur *= pow(2.0, exposition);

  // Autour de 0.5 : autour de 0.0, monter le contraste assombrirait toute l'image.
  couleur = (couleur - 0.5) * (1.0 + contraste) + 0.5;

  // Luminance perceptuelle (Rec. 709) et non moyenne des canaux : desaturer un lavis
  // de garance a la moyenne le rendrait plus clair qu'il ne l'est.
  float luminance = dot(couleur, vec3(0.2126, 0.7152, 0.0722));
  couleur = mix(vec3(luminance), couleur, 1.0 + saturation);

  couleur.r *= 1.0 + temperature * 0.2;
  couleur.b *= 1.0 - temperature * 0.2;

  gl_FragColor = vec4(clamp(couleur, 0.0, 1.0), pixel.a);
}
`

export interface Rendu {
  dessiner(source: TexImageSource, reglages: Reglages): void
  detruire(): void
}

export function creerRendu(canvas: HTMLCanvasElement): Rendu {
  const gl = canvas.getContext('webgl', { preserveDrawingBuffer: true })

  if (!gl) {
    throw new Error('WebGL indisponible sur cet appareil')
  }

  const programme = compiler(gl)
  gl.useProgram(programme)

  const tampon = gl.createBuffer()
  gl.bindBuffer(gl.ARRAY_BUFFER, tampon)
  gl.bufferData(gl.ARRAY_BUFFER, new Float32Array([-1, -1, 1, -1, -1, 1, 1, 1]), gl.STATIC_DRAW)

  const position = gl.getAttribLocation(programme, 'position')
  gl.enableVertexAttribArray(position)
  gl.vertexAttribPointer(position, 2, gl.FLOAT, false, 0, 0)

  const texture = gl.createTexture()
  gl.bindTexture(gl.TEXTURE_2D, texture)

  // Sans CLAMP_TO_EDGE et LINEAR, une texture dont les cotes ne sont pas des puissances
  // de deux — donc toute photo — rend un cadre noir en WebGL 1.
  gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_S, gl.CLAMP_TO_EDGE)
  gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_T, gl.CLAMP_TO_EDGE)
  gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MIN_FILTER, gl.LINEAR)
  gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MAG_FILTER, gl.LINEAR)

  return {
    dessiner(source, reglages) {
      gl.texImage2D(gl.TEXTURE_2D, 0, gl.RGBA, gl.RGBA, gl.UNSIGNED_BYTE, source)

      for (const [nom, valeur] of Object.entries(reglages)) {
        gl.uniform1f(gl.getUniformLocation(programme, nom), valeur)
      }

      gl.viewport(0, 0, canvas.width, canvas.height)
      gl.drawArrays(gl.TRIANGLE_STRIP, 0, 4)
    },

    detruire() {
      gl.deleteTexture(texture)
      gl.deleteBuffer(tampon)
      gl.deleteProgram(programme)
    },
  }
}

function compiler(gl: WebGLRenderingContext): WebGLProgram {
  const programme = gl.createProgram()

  if (!programme) {
    throw new Error('Programme WebGL impossible a creer')
  }

  for (const [type, source] of [
    [gl.VERTEX_SHADER, SOMMETS],
    [gl.FRAGMENT_SHADER, FRAGMENTS],
  ] as const) {
    const shader = gl.createShader(type)

    if (!shader) {
      throw new Error('Shader impossible a creer')
    }

    gl.shaderSource(shader, source)
    gl.compileShader(shader)

    if (!gl.getShaderParameter(shader, gl.COMPILE_STATUS)) {
      throw new Error(`Shader refuse : ${gl.getShaderInfoLog(shader)}`)
    }

    gl.attachShader(programme, shader)
  }

  gl.linkProgram(programme)

  if (!gl.getProgramParameter(programme, gl.LINK_STATUS)) {
    throw new Error(`Programme refuse : ${gl.getProgramInfoLog(programme)}`)
  }

  return programme
}
