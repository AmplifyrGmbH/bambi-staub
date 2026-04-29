import { useState } from 'react'
import { Link } from 'react-router-dom'

const stackImages = [
  { src: '/images/IMG_5775.jpeg', alt: 'Esszimmer mit Arvenholz' },
  { src: '/images/IMG_5750.jpeg', alt: 'Wohnbereich mit Holzvertäfelung' },
  { src: '/images/IMG_5730.jpeg', alt: 'Gemütliche Sitzecke' },
  { src: '/images/IMG_5700.jpeg', alt: 'Aussenansicht Chalet' },
  { src: '/images/IMG_5669.jpeg', alt: 'Balkon mit Waldblick' },
  { src: '/images/IMG_5675.jpeg', alt: 'Garten mit Steinofen' },
]

function PhotoStack() {
  const [current, setCurrent] = useState(0)
  const total = stackImages.length

  const prev = () => setCurrent(i => (i - 1 + total) % total)
  const next = () => setCurrent(i => (i + 1) % total)

  return (
    <div className="relative w-full h-[460px] overflow-hidden">
      {stackImages.map((img, i) => (
        <div
          key={img.src}
          className="absolute inset-0 transition-opacity duration-500"
          style={{ opacity: i === current ? 1 : 0, zIndex: i === current ? 1 : 0 }}
        >
          <img src={img.src} alt={img.alt} className="w-full h-full object-cover" />
        </div>
      ))}

      {/* Pfeile */}
      <button onClick={prev} className="absolute left-4 top-1/2 -translate-y-1/2 z-10 w-10 h-10 flex items-center justify-center bg-linen/80 hover:bg-linen transition-colors" aria-label="Zurück">
        <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" strokeWidth="1.5" className="w-4 h-4 text-charcoal">
          <path strokeLinecap="round" strokeLinejoin="round" d="M13 15l-5-5 5-5" />
        </svg>
      </button>
      <button onClick={next} className="absolute right-4 top-1/2 -translate-y-1/2 z-10 w-10 h-10 flex items-center justify-center bg-linen/80 hover:bg-linen transition-colors" aria-label="Weiter">
        <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" strokeWidth="1.5" className="w-4 h-4 text-charcoal">
          <path strokeLinecap="round" strokeLinejoin="round" d="M7 5l5 5-5 5" />
        </svg>
      </button>

      {/* Dots */}
      <div className="absolute bottom-4 left-1/2 -translate-x-1/2 z-10 flex gap-2">
        {stackImages.map((_, i) => (
          <button key={i} onClick={() => setCurrent(i)} className={`w-1.5 h-1.5 rounded-full transition-all ${i === current ? 'bg-linen w-4' : 'bg-linen/50'}`} aria-label={`Bild ${i + 1}`} />
        ))}
      </div>
    </div>
  )
}

const BOOKING_URL = 'https://booking.haus-bambi.ch'

const highlights = [
  {
    icon: (
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" className="w-7 h-7">
        <path strokeLinecap="round" strokeLinejoin="round" d="M3 12l9-9 9 9M5 10v9a1 1 0 001 1h4v-5h4v5h4a1 1 0 001-1v-9" />
      </svg>
    ),
    label: 'Pistenanschluss',
    text: 'Ski-in / Ski-out – direkt an der Piste von Sörenberg',
  },
  {
    icon: (
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" className="w-7 h-7">
        <path strokeLinecap="round" strokeLinejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
      </svg>
    ),
    label: 'Familienfreundlich',
    text: 'Platz für die ganze Familie – von Gross bis Klein',
  },
  {
    icon: (
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" className="w-7 h-7">
        <path strokeLinecap="round" strokeLinejoin="round" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" />
      </svg>
    ),
    label: 'Natur pur',
    text: 'UNESCO Biosphäre Entlebuch – Moorlandschaften & Alpenpanorama',
  },
  {
    icon: (
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" className="w-7 h-7">
        <path strokeLinecap="round" strokeLinejoin="round" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
      </svg>
    ),
    label: 'Arvenholz-Ambiente',
    text: 'Warmes, gemütliches Holzinterieur – typisch alpiner Charme',
  },
]

export default function Home() {
  return (
    <>
      {/* ── HERO ── */}
      <section className="relative h-screen min-h-[600px] flex items-center">
        <div className="absolute inset-0">
          <img
            src="/images/IMG_5679.jpeg"
            alt="Chalet Bambi – Alpenpanorama vom Balkon in Sörenberg"
            className="w-full h-full object-cover"
          />
          {/* Dunkler Verlauf oben für Nav-Lesbarkeit */}
          <div className="absolute inset-0 bg-gradient-to-b from-charcoal/50 via-transparent to-transparent" />
          {/* Dunkler Verlauf unten für Schrift-Lesbarkeit */}
          <div className="absolute inset-0 bg-gradient-to-t from-charcoal/60 via-transparent to-transparent" />
        </div>
        <div className="relative z-10 max-w-7xl mx-auto px-8 md:px-12 w-full">
          <p className="font-sans text-xs tracking-widest uppercase text-linen/60 mb-4">
            Sörenberg · UNESCO Biosphäre Entlebuch
          </p>
          <h1 className="font-serif text-4xl md:text-6xl font-light text-linen leading-snug max-w-xl">
            Chalet Bambi –<br />
            <span className="italic font-light text-linen/85 text-3xl md:text-5xl">
              Deine Auszeit in der<br />UNESCO Biosphäre Entlebuch
            </span>
          </h1>
        </div>
      </section>


      {/* ── EDITORIAL SPLIT ── */}
      <section className="bg-linen-dark py-24 px-6 overflow-hidden">
        <div className="max-w-7xl mx-auto grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
          <PhotoStack />

          {/* Text */}
          <div className="lg:pl-8">
            <p className="section-label">Das Haus</p>
            <div className="divider mb-6" />
            <h2 className="section-heading mb-6">
              Wo Tradition auf alpine Gemütlichkeit trifft
            </h2>
            <p className="font-sans text-base text-charcoal-light leading-relaxed mb-4">
              Chalet Bambi ist mehr als ein Ferienhaus – es ist ein Ort voller Erinnerungen.
              Erbaut von unseren Grosseltern und bis heute mit Herzblut gepflegt, empfängt
              es Generationen von Gästen mit seiner warmen Atmosphäre.
            </p>
            <p className="font-sans text-base text-charcoal-light leading-relaxed mb-8">
              Das handgefertigte Arvenholz-Interieur, die knisternde Wärme des Ofens und
              der Blick in die Waldlandschaft Sörenbergs schaffen eine einzigartige
              Kulisse für unvergessliche Ferien.
            </p>
            <Link to="/bambi" className="btn-outline-dark">
              Alle Zimmer & Ausstattung
            </Link>
          </div>
        </div>
      </section>

      {/* ── LAGE & UMGEBUNG ── */}
      <section className="bg-forest text-linen py-24 px-6">
        <div className="max-w-7xl mx-auto">

          {/* Text */}
          <div className="mb-14">
            <p className="font-sans text-xs tracking-widest uppercase text-linen/40 mb-4">Umgebung & Aktivitäten</p>
            <div className="w-12 h-px bg-linen/30 mb-6" />
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-8 items-end">
              <h2 className="font-serif text-4xl md:text-5xl font-light leading-tight">
                Im Herzen der UNESCO Biosphäre Entlebuch
              </h2>
              <p className="font-sans text-base text-linen/70 leading-relaxed">
                  Sörenberg liegt auf 1'160 m ü. M. am Fuss des Brienzer Rothhorns. Die Region
                  gehört zur UNESCO Biosphäre Entlebuch – ein einzigartiges Mosaik aus
                  Moorlandschaften, Alpwiesen und urigen Wäldern.
                </p>
            </div>
          </div>

          {/* Winter / Sommer Bilder */}
          <div className="grid grid-cols-1 md:grid-cols-2 gap-1">
            <div className="img-hover relative h-80 overflow-hidden">
              <img
                src="/images/IMG_6242.jpeg"
                alt="Winter in Sörenberg – Skifahren am Brienzer Rothorn"
                className="w-full h-full object-cover"
              />
              <div className="absolute inset-0 bg-gradient-to-t from-charcoal/70 to-transparent" />
              <div className="absolute bottom-0 left-0 p-8">
                <p className="font-sans text-xs tracking-widest uppercase text-linen/60 mb-1">Winter</p>
                <h3 className="font-serif text-2xl font-light text-linen">Ski & Schneeschuh</h3>
              </div>
            </div>
            <div className="img-hover relative h-80 overflow-hidden">
              <img
                src="/images/IMG_5675.jpeg"
                alt="Sommer in Sörenberg – Wandern in der Biosphäre"
                className="w-full h-full object-cover"
              />
              <div className="absolute inset-0 bg-gradient-to-t from-charcoal/70 to-transparent" />
              <div className="absolute bottom-0 left-0 p-8">
                <p className="font-sans text-xs tracking-widest uppercase text-linen/60 mb-1">Sommer</p>
                <h3 className="font-serif text-2xl font-light text-linen">Wandern & Natur</h3>
              </div>
            </div>
          </div>

          {/* Button unter den Bildern */}
          <div className="mt-10 flex justify-center">
            <Link to="/lage" className="btn-outline">
              Lage entdecken
            </Link>
          </div>

        </div>
      </section>

      {/* ── ÜBER UNS ── */}
      <section className="bg-linen-dark py-24 px-6">
        <div className="max-w-7xl mx-auto grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
          <div className="img-hover h-80 md:h-[420px] shadow-lg">
            <img
              src="/images/IMG_5730.jpeg"
              alt="Gemütliches Wohnzimmer im Chalet Bambi"
              className="w-full h-full object-cover"
            />
          </div>
          <div className="lg:pl-8">
            <p className="section-label">Über uns</p>
            <div className="divider mb-6" />
            <h2 className="section-heading mb-6">
              Drei Generationen, ein Chalet
            </h2>
            <p className="font-sans text-base text-charcoal-light leading-relaxed mb-8">
              Chalet Bambi wurde von unseren Grosseltern erbaut und wird bis heute mit
              Herzblut gepflegt. Was als Familienrückzugsort begann, ist heute ein Ort
              voller Geschichten – und wartet darauf, neue zu schreiben.
            </p>
            <Link to="/ueber-uns" className="btn-outline-dark">
              Unsere Geschichte
            </Link>
          </div>
        </div>
      </section>
    </>
  )
}
