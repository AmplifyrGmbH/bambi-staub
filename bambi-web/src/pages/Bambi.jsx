import { useState } from 'react'


const ausstattung = [
  { kategorie: 'Entertainment', items: ['Flachbildschirm-TV', 'Sat-TV', 'Radio', 'CD-Player', 'Gratis WLAN'] },
  { kategorie: 'Küche', items: ['Nespresso Maschine', 'Bialetti Moka & Milchschäumer', 'Herd & Backofen', 'Dunstabzugshaube', 'Toaster', 'Wasserkocher', 'Kühlschrank & Gefrierfach'] },
  { kategorie: 'Komfort', items: ['Holzofen', 'Arvenholz-Vertäfelung', 'Sonnenterrasse (20 m²)', 'Heizung in allen Räumen', 'Gemütliche Sitzecken'] },
  { kategorie: 'Praktisches', items: ['Parkplatz (1 Fahrzeug)', 'Staubsauger', 'Kinderstuhl & -bett auf Anfrage'] },
  { kategorie: 'Aussen', items: ['Garten (650 m²)', 'Grillplatz', 'Outdoormöbel', 'Direkter Zugang zur Waldemme', 'Gedeckter Sitzplatz unter Terrasse'] },
]

const zimmer = [
  {
    name: 'Eingangsbereich',
    beschreibung: 'Der Eingangsbereich empfängt Sie mit dem typischen Chalet-Charme – Holz, Stein und alpinem Flair.',
    bilder: [
      '/images/eingangsbereich/04IMG_2359.jpg', '/images/eingangsbereich/05IMG_2358.jpg',
      '/images/eingangsbereich/06IMG_2311.jpg', '/images/eingangsbereich/06IMG_2314.jpg',
      '/images/IMG_5779.jpeg', '/images/IMG_5782.jpeg',
      '/images/IMG_5785.jpeg', '/images/IMG_5786.jpeg',
    ],
  },
  {
    name: 'WC & Bad',
    beschreibung: 'Gepflegte Badezimmer mit allem Notwendigen für einen angenehmen Aufenthalt.',
    bilder: [
      '/images/wc/07Badezimmer_2025_01.JPG', '/images/wc/08IMG_6141.JPG',
      '/images/wc/09Badezimmer_2025_02.JPG',
      '/images/IMG_5745.jpeg', '/images/IMG_5746.jpeg',
    ],
  },
  {
    name: 'Wohn- & Esszimmer',
    beschreibung: 'Das Herzstück des Hauses: warmes Arvenholz, ein knisternder Holzofen, ein geräumiger Esstisch und eine gemütliche Sofaecke – ideal für gemeinsame Abende nach einem langen Skitag.',
    bilder: [
      '/images/wohnzimmer/00IMG_0779 - Kopie.JPG', '/images/wohnzimmer/02IMG_0780 - Kopie.JPG',
      '/images/wohnzimmer/02IMG_0781.JPG', '/images/wohnzimmer/02IMG_0782.JPG',
      '/images/wohnzimmer/03IMG_0570.jpg',
      '/images/IMG_5775.jpeg', '/images/IMG_5748.jpeg', '/images/IMG_5750.jpeg',
      '/images/IMG_5752.jpeg', '/images/IMG_5753.jpeg', '/images/IMG_5755.jpeg',
      '/images/IMG_5757.jpeg', '/images/IMG_5759.jpeg', '/images/IMG_5761.jpeg',
      '/images/IMG_5763.jpeg', '/images/IMG_5764.jpeg', '/images/IMG_5767.jpeg',
      '/images/IMG_5772.jpeg', '/images/IMG_5777.jpeg', '/images/IMG_5778.jpeg',
    ],
  },
  {
    name: 'Küche',
    beschreibung: 'Vollausgestattete Küche mit allem was das Herz begehrt – inklusive Fondue-Set und Raclette-Gerät für typisch schweizerische Abende.',
    bilder: [
      '/images/kueche/10IMG_2307.jpg', '/images/kueche/11IMG_2308.jpg',
      '/images/IMG_5741.jpeg', '/images/IMG_5743.jpeg',
    ],
  },
  {
    name: 'Schlafzimmer',
    beschreibung: 'Das Schlafzimmer bietet 4 Etagenbetten – ideal für Familien mit Kindern. Ruhige Atmosphäre, natürliche Materialien und die heilende Kraft des Arvenholzes. Im Wohnzimmer und im Untergeschoss befinden sich zusätzlich je ein ausziehbares Schlafsofa.',
    bilder: [
      '/images/schlafzimmer/04IMG_0573.jpg', '/images/schlafzimmer/12IMG_2330.jpg',
      '/images/schlafzimmer/IMG_0572.jpg', '/images/schlafzimmer/IMG_5776.jpeg',
      '/images/schlafzimmer/Treppe_Oben.jpg', '/images/schlafzimmer/Treppe_mit_Zimmer_Happyholiday.jpg',
      '/images/IMG_5773.jpeg',
    ],
  },
  {
    name: 'Untergeschoss',
    beschreibung: 'Das gemütliche Untergeschoss bietet zusätzlichen Wohnraum mit einem Schlafsofa, Sesseln und TV. Der Zugang erfolgt vom Schlafzimmer aus über eine Bodenklappe mit Leiter – oder von aussen über die Gartentreppe.',
    bilder: [
      '/images/untergeschoss/01IMG_2376.jpg', '/images/untergeschoss/03IMG_2378.jpg',
      '/images/untergeschoss/IMG_0574.jpg', '/images/untergeschoss/IMG_0575.jpg',
      '/images/untergeschoss/IMG_0580.jpg', '/images/untergeschoss/Treppe_unten.JPG',
      '/images/untergeschoss/Treppe_von_hinten.JPG',
      '/images/IMG_5730.jpeg', '/images/IMG_5718.jpeg', '/images/IMG_5720 (1).jpeg',
      '/images/IMG_5721 (1).jpeg', '/images/IMG_5723.jpeg', '/images/IMG_5724.jpeg',
      '/images/IMG_5725.jpeg', '/images/IMG_5727.jpeg', '/images/IMG_5732.jpeg',
      '/images/IMG_5734.jpeg', '/images/IMG_5735.jpeg', '/images/IMG_5736.jpeg',
    ],
  },
  {
    name: 'Aussenbereich',
    beschreibung: 'Die 20 m² grosse Sonnenterrasse zeigt Richtung Süden – mit Blick auf das Brienzer Rothorn und die Waldemme. Unter der Terrasse befindet sich ein gedeckter Sitzplatz mit Grillzubehör. Das Grundstück bietet direkten Zugang zum Bach, an dem Kinder bei tiefem Wasserstand spielen können.',
    bilder: [
      '/images/aussenbereich/01IMG_2321.jpg', '/images/aussenbereich/02IMG_2429.jpg',
      '/images/aussenbereich/03IMG_2318.jpg', '/images/aussenbereich/04IMG_2432.jpg',
      '/images/aussenbereich/05IMG_2436.jpg', '/images/aussenbereich/07IMG_2452.jpg',
      '/images/aussenbereich/08IMG_2404.jpg', '/images/aussenbereich/09IMG_2438.jpg',
      '/images/aussenbereich/09IMG_2443.jpg', '/images/aussenbereich/12IMG_2393.jpg',
      '/images/aussenbereich/13IMG_2451.jpg', '/images/aussenbereich/14IMG_2454.jpg',
      '/images/aussenbereich/15IMG_2355.jpg', '/images/aussenbereich/16IMG_2349.jpg',
      '/images/aussenbereich/17IMG_2337.jpg', '/images/aussenbereich/18IMG_2356.jpg',
      '/images/aussenbereich/19IMG_2338.jpg', '/images/aussenbereich/20IMG_2418.jpg',
      '/images/aussenbereich/21IMG_2339.jpg',
      '/images/IMG_5669.jpeg', '/images/IMG_5671.jpeg', '/images/IMG_5672.jpeg',
      '/images/IMG_5675.jpeg', '/images/IMG_5676.jpeg', '/images/IMG_5677.jpeg',
      '/images/IMG_5679.jpeg', '/images/IMG_5680.jpeg', '/images/IMG_5684.jpeg',
      '/images/IMG_5685.jpeg', '/images/IMG_5687.jpeg', '/images/IMG_5689.jpeg',
      '/images/IMG_5691.jpeg', '/images/IMG_5696.jpeg', '/images/IMG_5699.jpeg',
      '/images/IMG_5700.jpeg',
    ],
  },
]

function RoomSlider({ bilder, name }) {
  const [idx, setIdx] = useState(0)
  const total = bilder.length
  return (
    <div className="relative h-80 md:h-96">
      <div className="absolute inset-0 mx-8 shadow-lg overflow-hidden">
        {bilder.map((src, i) => (
          <div key={src} className="absolute inset-0" style={{ opacity: i === idx ? 1 : 0, zIndex: i === idx ? 1 : 0 }}>
            <img src={src} alt={`${name} – Bild ${i + 1}`} className="w-full h-full object-cover" />
          </div>
        ))}
        {total > 1 && (
          <div className="absolute bottom-4 left-1/2 -translate-x-1/2 z-10 flex gap-2">
            {bilder.map((_, i) => (
              <button key={i} onClick={() => setIdx(i)} className={`h-1.5 rounded-full ${i === idx ? 'w-4 bg-linen' : 'w-1.5 bg-linen/50'}`} aria-label={`Bild ${i + 1}`} />
            ))}
          </div>
        )}
      </div>
      {total > 1 && (
        <>
          <button onClick={() => setIdx(i => (i - 1 + total) % total)} className="absolute left-0 top-1/2 -translate-y-1/2 z-10 w-14 h-14 flex items-center justify-center bg-linen shadow-md" aria-label="Zurück">
            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" strokeWidth="1.5" className="w-4 h-4 text-charcoal"><path strokeLinecap="round" strokeLinejoin="round" d="M13 15l-5-5 5-5" /></svg>
          </button>
          <button onClick={() => setIdx(i => (i + 1) % total)} className="absolute right-0 top-1/2 -translate-y-1/2 z-10 w-14 h-14 flex items-center justify-center bg-linen shadow-md" aria-label="Weiter">
            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" strokeWidth="1.5" className="w-4 h-4 text-charcoal"><path strokeLinecap="round" strokeLinejoin="round" d="M7 5l5 5-5 5" /></svg>
          </button>
        </>
      )}
    </div>
  )
}

export default function Bambi() {

  return (
    <>
      {/* ── HERO ── */}
      <section className="relative h-[60vh] min-h-[400px] flex items-end">
        <div className="absolute inset-0">
          <img
            src="/images/hero/Hero_Haus.JPG"
            alt="Chalet Bambi – warmes Wohnzimmer"
            className="w-full h-full object-cover"
          />
          <div className="absolute inset-0 bg-gradient-to-t from-charcoal/75 via-charcoal/20 to-transparent" />
        </div>
        <div className="relative z-10 max-w-7xl mx-auto px-6 pb-16">
          <p className="section-label text-linen/50">Bambi</p>
          <h1 className="font-serif text-5xl md:text-6xl font-light text-linen leading-tight">
            Chalet Bambi
          </h1>
          <p className="font-sans text-lg text-linen/70 mt-3">Zimmer & Ausstattung</p>
        </div>
      </section>


      {/* ── ZIMMER ── */}
      <section id="raeumlichkeiten" className="bg-cream py-24 px-6">
        <div className="max-w-7xl mx-auto">
          <div className="mb-16">
            <p className="section-label">Räumlichkeiten</p>
            <div className="divider mb-6" />
            <h2 className="section-heading">Gemütliches Arvenholz-Ambiente</h2>
          </div>

          <div className="flex flex-col gap-24">
            {zimmer.map(({ name, beschreibung, bilder }, i) => (
              <div key={name} className="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
                <div className={i % 2 !== 0 ? 'lg:order-2' : ''}>
                  <RoomSlider bilder={bilder} name={name} />
                </div>
                <div className={i % 2 !== 0 ? 'lg:order-1 lg:pr-8' : 'lg:pl-8'}>
                  <p className="font-sans text-xs tracking-widest uppercase text-forest-muted mb-3">
                    {String(i + 1).padStart(2, '0')}
                  </p>
                  <h3 className="font-serif text-3xl font-light text-charcoal mb-4">{name}</h3>
                  <div className="w-8 h-px bg-stone-light mb-5" />
                  <p className="font-sans text-base text-charcoal-light leading-relaxed">{beschreibung}</p>
                </div>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* ── AUSSTATTUNG ── */}
      <section id="ausstattung" className="bg-linen py-24 px-6">
        <div className="max-w-7xl mx-auto">
          <div className="mb-16">
            <p className="section-label">Ausstattung</p>
            <div className="divider mb-6" />
            <h2 className="section-heading">Was Sie erwartet</h2>
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-8">
            {ausstattung.map(({ kategorie, items }) => (
              <div key={kategorie}>
                <h3 className="font-sans text-xs tracking-widest uppercase text-forest-muted mb-4">
                  {kategorie}
                </h3>
                <ul className="flex flex-col gap-2.5">
                  {items.map((item) => (
                    <li key={item} className="flex items-start gap-2.5">
                      <span className="text-forest mt-1 flex-shrink-0">
                        <svg viewBox="0 0 12 12" fill="none" stroke="currentColor" strokeWidth="1.5" className="w-3 h-3">
                          <path strokeLinecap="round" strokeLinejoin="round" d="M2 6l3 3 5-5" />
                        </svg>
                      </span>
                      <span className="font-sans text-sm text-charcoal-light">{item}</span>
                    </li>
                  ))}
                </ul>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* ── HAUSORDNUNG ── */}
      <section id="hausordnung" className="bg-cream py-24 px-6">
        <div className="max-w-3xl mx-auto">
          <div className="mb-12">
            <p className="section-label">Hausordnung</p>
            <div className="divider mb-6" />
            <h2 className="section-heading">Damit sich alle wohlfühlen</h2>
          </div>
          <div className="grid grid-cols-1 md:grid-cols-2 gap-8">
            {[
              { titel: 'An- & Abreise', text: 'Check-in ab 15:00 Uhr, Check-out bis 10:00 Uhr. Bei Abweichungen bitte frühzeitig absprechen.' },
              { titel: 'Mindestaufenthalt', text: 'Mindestens 2 Nächte. Buchung direkt über Familie Staub.' },
              { titel: 'Haustiere', text: 'Haustiere sind leider nicht gestattet.' },
              { titel: 'Rauchen', text: 'Im gesamten Chalet ist das Rauchen nicht erlaubt. Draussen bitte auf Aschenbecher achten.' },
              { titel: 'Ruhezeiten', text: 'Von 22:00 bis 07:00 Uhr bitten wir um Rücksichtnahme auf die Nachbarn.' },
              { titel: 'Reinigung', text: 'Das Chalet ist bei Abreise besenrein zu hinterlassen. Bettwäsche und Handtücher bitte zusammenlegen.' },
              { titel: 'Skikeller', text: 'Der Skikeller steht exklusiv zur Verfügung. Bitte Ski und Ausrüstung ordentlich versorgen.' },
              { titel: 'Schäden', text: 'Schäden bitte umgehend melden. Wir finden gemeinsam eine unkomplizierte Lösung.' },
            ].map(({ titel, text }) => (
              <div key={titel} className="flex items-start gap-4">
                <span className="text-forest mt-1 flex-shrink-0">
                  <svg viewBox="0 0 12 12" fill="none" stroke="currentColor" strokeWidth="1.5" className="w-3 h-3">
                    <path strokeLinecap="round" strokeLinejoin="round" d="M2 6l3 3 5-5" />
                  </svg>
                </span>
                <div>
                  <p className="font-sans text-sm font-medium text-charcoal mb-1">{titel}</p>
                  <p className="font-sans text-sm text-charcoal-light leading-relaxed">{text}</p>
                </div>
              </div>
            ))}
          </div>
        </div>
      </section>
    </>
  )
}
