const activities = [
  {
    season: 'Winter',
    icon: '❄',
    items: [
      { titel: 'Skifahren', text: '13 Anlagen zwischen 1165 m und 2340 m Höhe – vom Chalet sind es ca. 8–10 Minuten zu Fuss bis zur nächsten Bahn. Pisten für alle Niveaus.' },
      { titel: 'Schneeschuh & Winterwandern', text: 'Markierte Schneeschuhrouten durch verschneite Wälder und Moorlandschaften.' },
      { titel: 'Schlitteln', text: 'Natürliche Rodelabfahrten für Gross und Klein – familienfreundlicher Winterspass.' },
      { titel: 'Langlaufen', text: 'Gepflegte Langlaufloipen im Entlebuch für Klassisch und Skating.' },
      { titel: 'Ski- & Snowboardkurse', text: 'Schneesportschule direkt vor Ort – ideal für Anfänger und zur Technikverbesserung.' },
    ],
  },
  {
    season: 'Sommer',
    icon: '☀',
    items: [
      { titel: 'Wandern', text: 'Ausgedehntes Wanderwegnetz durch die Biosphäre – von gemütlichen Spazierwegen bis zu Gipfeltouren.' },
      { titel: 'Mountainbiken', text: 'Abwechslungsreiche Bike-Trails durch Wälder und Alpwiesen für alle Fahrkönner.' },
      { titel: 'Bergjuwel Eisee', text: 'Idyllischer Bergsee auf ca. 1\'650 m ü. M. – perfektes Ziel für eine Wanderung mit Ausblick.' },
      { titel: 'Moorwelten Rossweid', text: 'Einzigartiges Moorerlebnis mit dem Erlebnisweg «Mooraculum» – Naturwunder hautnah.' },
      { titel: 'Sommerrodelbahn Rischli', text: 'Nervenkitzel auf der Sommerrodelbahn – Spass für die ganze Familie.' },
      { titel: 'Fischen am Eisee', text: 'Angeln in klarem Bergseewasser – Erholung pur in unberührter Natur.' },
      { titel: 'Genusswanderungen', text: 'Geführte Touren zu lokalen Alpbeizen und Käsereien – Sörenberger Gastfreundschaft erleben.' },
    ],
  },
  {
    season: 'Ganzjährig',
    icon: '◆',
    items: [
      { titel: 'Brienzer Rothorn Bahn', text: 'Die Dampfzahnradbahn auf den Rothorn (2\'348 m) – seit 1892 ein unvergessliches Erlebnis mit Panoramablick auf Alpen und Voralpen.' },
      { titel: 'UNESCO Biosphäre', text: 'Exkursionen, Kurse und geführte Erlebnisse in einer der artenreichsten Regionen der Schweiz.' },
      { titel: 'Grillstellen & Picknick', text: 'Zahlreiche Grillstellen und Picknickplätze inmitten der Natur.' },
      { titel: 'Lokale Produkte', text: 'Frischer Alpkäse, Entlebucher Spezialitäten und regionale Küche direkt vom Produzenten.' },
    ],
  },
]

export default function Lage() {
  return (
    <>
      {/* ── HERO ── */}
      <section className="relative h-[60vh] min-h-[400px] flex items-end">
        <div className="absolute inset-0">
          <img
            src="/images/IMG_5685.jpeg"
            alt="Panoramablick von Sörenberg"
            className="w-full h-full object-cover"
          />
          <div className="absolute inset-0 bg-gradient-to-t from-charcoal/75 via-charcoal/20 to-transparent" />
        </div>
        <div className="relative z-10 max-w-7xl mx-auto px-6 pb-16">
          <p className="section-label text-linen/50">Standort</p>
          <h1 className="font-serif text-5xl md:text-6xl font-light text-linen leading-tight">
            Umgebung & Aktivitäten
          </h1>
          <p className="font-sans text-lg text-linen/70 mt-3">
            Rothornstrasse 40, 6174 Sörenberg
          </p>
        </div>
      </section>

      {/* ── UNESCO BIOSPHÄRE ── */}
      <section id="biosphaere" className="bg-linen py-24 px-6">
        <div className="max-w-7xl mx-auto grid grid-cols-1 lg:grid-cols-2 gap-16 items-center">
          <div>
            <p className="section-label">UNESCO Biosphäre Entlebuch</p>
            <div className="divider mb-6" />
            <h2 className="section-heading mb-6">
              Eine der artenreichsten Regionen der Schweiz
            </h2>
            <p className="font-sans text-base text-charcoal-light leading-relaxed mb-4">
              Das Entlebuch ist seit 2001 als UNESCO Biosphäre anerkannt – eine der ersten
              Biosphären weltweit, die durch eine Volksabstimmung entstanden ist. Riesige
              Moorlandschaften, tiefe Wälder und majestätische Alpenketten prägen
              diese einzigartige Landschaft.
            </p>
            <p className="font-sans text-base text-charcoal-light leading-relaxed mb-8">
              Sörenberg liegt auf 1'160 m ü. M. im Herzen dieser Biosphäre, umgeben von
              markanten Gipfeln: dem Brienzer Rothorn (2'348 m), der Schratteflue – Hängst
              (2'091 m) und der Haglere (1'948 m). Die Dampfzahnradbahn auf den Rothorn,
              die seit 1892 den Gipfel erklimmt, ist bis heute ein unvergessliches Erlebnis.
            </p>
            <div className="grid grid-cols-3 gap-6 pt-6 border-t border-stone-light/40">
              {[
                { zahl: '1\'160', einheit: 'm ü. M.', label: 'Höhe Sörenberg' },
                { zahl: '2\'348', einheit: 'm ü. M.', label: 'Brienzer Rothorn' },
                { zahl: '13', einheit: 'Anlagen', label: 'Skigebiet' },
              ].map(({ zahl, einheit, label }) => (
                <div key={label} className="text-center">
                  <p className="font-serif text-3xl font-light text-forest">{zahl}</p>
                  <p className="font-sans text-xs text-forest-muted mb-1">{einheit}</p>
                  <p className="font-sans text-xs text-stone tracking-wide">{label}</p>
                </div>
              ))}
            </div>
          </div>

          <div className="grid grid-cols-2 gap-3">
            <div className="img-hover h-64">
              <img src="/images/IMG_5696.jpeg" alt="Natur im Entlebuch" className="w-full h-full object-cover" />
            </div>
            <div className="img-hover h-64 mt-8">
              <img src="/images/IMG_5699.jpeg" alt="Alpenpanorama Sörenberg" className="w-full h-full object-cover" />
            </div>
          </div>
        </div>
      </section>

      {/* ── ACTIVITIES ── */}
      <section id="winter" className="bg-cream py-24 px_6">
        <div className="max-w-7xl mx-auto px-6">
          <div className="mb-16 text-center">
            <p className="section-label">Aktivitäten</p>
            <div className="divider mx-auto mb-6" />
            <h2 className="section-heading">Das ganze Jahr erlebenswert</h2>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-3 gap-8">
            {activities.map(({ season, icon, items }) => (
              <div key={season} id={season === 'Sommer' ? 'sommer' : undefined} className="bg-linen p-8">
                <div className="flex items-center gap-3 mb-6">
                  <span className="text-forest text-2xl">{icon}</span>
                  <h3 className="font-serif text-2xl font-light text-charcoal">{season}</h3>
                </div>
                <ul className="flex flex-col gap-5">
                  {items.map(({ titel, text }) => (
                    <li key={titel} className="flex items-start gap-3">
                      <span className="text-forest mt-1 flex-shrink-0">
                        <svg viewBox="0 0 12 12" fill="none" stroke="currentColor" strokeWidth="1.5" className="w-3 h-3">
                          <path strokeLinecap="round" strokeLinejoin="round" d="M2 6l3 3 5-5" />
                        </svg>
                      </span>
                      <div>
                        <p className="font-sans text-sm font-medium text-charcoal">{titel}</p>
                        <p className="font-sans text-xs text-charcoal-light leading-relaxed mt-0.5">{text}</p>
                      </div>
                    </li>
                  ))}
                </ul>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* ── RESTAURANTS ── */}
      <section id="kulinarik" className="bg-linen py-24 px-6">
        <div className="max-w-7xl mx-auto">
          <div className="mb-16">
            <p className="section-label">Kulinarik</p>
            <div className="divider mb-6" />
            <h2 className="section-heading">Unsere Restaurantempfehlungen</h2>
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8">
            {[
              {
                name: 'Rischli',
                beschreibung: 'Beliebtes Bergrestaurant mit herrlicher Aussicht – ideal nach einem langen Ski- oder Wandertag. Herzhafte Bergküche in gemütlicher Atmosphäre.',
              },
              {
                name: 'Bergwelten Salwideli',
                beschreibung: 'Auf dem Sattel zwischen Sörenberg und dem Entlebuch gelegen. Einzigartige Lage mit Panoramaaussicht und typisch schweizer Gerichten.',
              },
              {
                name: 'Treffpunkt',
                beschreibung: 'Das zentrale Dorfrestaurant in Sörenberg – gesellig, unkompliziert und immer eine gute Wahl für die ganze Familie.',
              },
              {
                name: 'Alpenrösli',
                beschreibung: 'Klassisches Bergrestaurant mit Tradition. Frische lokale Küche, freundlicher Service und das typische Sörenberger Flair.',
              },
            ].map(({ name, beschreibung }) => (
              <div key={name} className="bg-cream p-7">
                <h3 className="font-serif text-xl font-light text-charcoal mb-3">{name}</h3>
                <div className="w-6 h-px bg-forest mb-4" />
                <p className="font-sans text-sm text-charcoal-light leading-relaxed">{beschreibung}</p>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* ── ANREISE ── */}
      <section id="anreise" className="bg-cream py-24 px-6">
        <div className="max-w-7xl mx-auto">
          <div className="mb-12">
            <p className="section-label">Anreise</p>
            <div className="divider mb-6" />
            <h2 className="section-heading">So finden Sie uns</h2>
          </div>

          <div className="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-12">
            {[
              {
                title: 'Mit dem Auto',
                text: 'A2 Richtung Luzern, Ausfahrt Wolhusen, dann via Entlebuch nach Flühli und Sörenberg. Ca. 40 min ab Luzern.',
              },
              {
                title: 'Mit dem ÖV',
                text: 'Zug nach Schüpfheim, dann mit dem Bus Richtung Sörenberg (Entlebuch Bahn). Haltestelle Sörenberg, Dorf.',
              },
              {
                title: 'Adresse',
                text: 'Rothornstrasse 40\n6174 Sörenberg\nSchweiz',
              },
            ].map(({ title, text }) => (
              <div key={title} className="p-8 bg-linen">
                <h3 className="font-serif text-xl font-light text-charcoal mb-3">{title}</h3>
                <p className="font-sans text-sm text-charcoal-light leading-relaxed whitespace-pre-line">{text}</p>
              </div>
            ))}
          </div>

          {/* Google Maps Embed */}
          <div className="w-full h-96 bg-stone-light/20 overflow-hidden shadow-lg">
            <iframe
              title="Chalet Bambi – Rothornstrasse 40, Sörenberg"
              src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d2699.6!2d8.0316!3d46.8167!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x478545e67a3b43bd%3A0x3a0b3a3b3a3b3a3b!2sRothornstrasse%2040%2C%206174%20S%C3%B6renberg!5e0!3m2!1sde!2sch!4v1620000000000!5m2!1sde!2sch"
              width="100%"
              height="100%"
              style={{ border: 0 }}
              allowFullScreen=""
              loading="lazy"
              referrerPolicy="no-referrer-when-downgrade"
            />
          </div>
        </div>
      </section>
    </>
  )
}
