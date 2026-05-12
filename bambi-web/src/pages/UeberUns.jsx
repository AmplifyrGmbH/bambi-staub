export default function UeberUns() {
  return (
    <>
      {/* ── HERO ── */}
      <section className="relative h-[60vh] min-h-[400px] flex items-end">
        <div className="absolute inset-0">
          <img
            src="/images/hero/Hero_Über_uns.JPG"
            alt="Gemütliche Sitzecke im Chalet Bambi"
            className="w-full h-full object-cover"
          />
          <div className="absolute inset-0 bg-gradient-to-t from-charcoal/75 via-charcoal/20 to-transparent" />
        </div>
        <div className="relative z-10 max-w-7xl mx-auto px-6 pb-16">
          <p className="section-label text-linen/50">Die Familie</p>
          <h1 className="font-serif text-5xl md:text-6xl font-light text-linen leading-tight">
            Über uns
          </h1>
          <p className="font-sans text-lg text-linen/70 mt-3">
            Drei Generationen, ein Haus, unzählige Erinnerungen
          </p>
        </div>
      </section>

      {/* ── GESCHICHTE & WURZELN ── */}
      <section id="geschichte" className="bg-linen py-24 px-6">
        <div className="max-w-7xl mx-auto grid grid-cols-1 lg:grid-cols-2 gap-16 items-start">

          {/* Text */}
          <div>
            <p className="section-label">Geschichte & Wurzeln</p>
            <div className="divider mb-6" />
            <h2 className="section-heading mb-8">
              Mit Herzblut erbaut – mit Liebe weitergeführt
            </h2>

            <div className="flex flex-col gap-6">
              <p className="font-sans text-base text-charcoal-light leading-relaxed">
                Chalet Bambi hat eine lange Geschichte. Es waren unsere Grosseltern, die
                das Haus in Sörenberg aufgebaut und ihm seinen unverwechselbaren Charakter
                gegeben haben. Mit eigenen Händen, viel Leidenschaft und einem Gespür für
                Qualität entstanden die handgefertigte Arvenholz-Vertäfelung, die warmen
                Holzböden und die gemütliche Atmosphäre, die noch heute jeden Gast empfängt.
              </p>
              <p className="font-sans text-base text-charcoal-light leading-relaxed">
                Ihre Liebe zum Detail, ihr Gespür für Qualität und ihre Freude an der
                Gastfreundschaft sind bis heute in jedem Winkel des Hauses zu spüren.
                Was als Rückzugsort für die eigene Familie begann, wurde über die Jahrzehnte
                zu einem Ort, an dem viele Generationen unvergessliche Momente erlebt haben.
              </p>
              <p className="font-sans text-base text-charcoal-light leading-relaxed">
                Heute pflegen wir, die Familie Staub, dieses Erbe mit der gleichen
                Sorgfalt und Herzlichkeit weiter. Wir haben das Haus behutsam modernisiert,
                ohne seinen Charakter zu verlieren: Schnelles WLAN trifft auf handgeschnitztes
                Holz, moderner Komfort auf alpine Tradition.
              </p>
              <p className="font-sans text-base text-charcoal-light leading-relaxed">
                Wenn Sie Chalet Bambi besuchen, werden Sie nicht nur ein Ferienhaus mieten –
                Sie werden Teil einer Geschichte, die weitergeschrieben wird.
              </p>
            </div>
          </div>

          {/* Bilder – Chalet + Grosseltern */}
          <div className="flex flex-col gap-4">
            <div className="img-hover shadow-lg overflow-hidden">
              <img
                src="/images/aussenbereich/Chalet_Bambi_Titelseite_Neu_2025.JPG"
                alt="Chalet Bambi – Aussenansicht 2025"
                className="w-full h-72 object-cover object-center"
              />
            </div>
            <div className="grid grid-cols-2 gap-4">
              <div className="img-hover shadow overflow-hidden">
                <img
                  src="/images/grosseltern/bde362e6-58ad-4b12-b5d7-4fb0a39f899e.jpeg"
                  alt="Die Grosseltern – Erbauer des Chalet Bambi"
                  className="w-full h-56 object-cover object-center"
                />
              </div>
              <div className="img-hover shadow overflow-hidden">
                <img
                  src="/images/grosseltern/bb3ab447-68f9-40c3-b53e-089bc2857c40.jpeg"
                  alt="Die Grosseltern vor dem Chalet"
                  className="w-full h-56 object-cover object-[center_15%]"
                />
              </div>
            </div>
            <div className="img-hover shadow overflow-hidden">
              <img
                src="/images/grosseltern/d191f743-8d2c-4737-a010-666e22d1aef6.jpeg"
                alt="Grossvater beim Abendessen im Chalet-Garten"
                className="w-full h-52 object-cover object-[center_20%]"
              />
            </div>
            <p className="font-sans text-xs text-stone text-center tracking-wide italic">
              Die Grosseltern – Erbauer und Seele des Chalet Bambi
            </p>
          </div>
        </div>
      </section>

      {/* ── FAMILIE STAUB ── */}
      <section id="familie-staub" className="bg-cream py-24 px-6">
        <div className="max-w-7xl mx-auto">
          <div className="mb-16 text-center">
            <p className="section-label">Die Familie</p>
            <div className="divider mx-auto mb-6" />
            <h2 className="section-heading max-w-2xl mx-auto">
              Familie Staub – Ihre Gastgeber
            </h2>
            <p className="font-sans text-base text-charcoal-light max-w-2xl mx-auto mt-4 leading-relaxed">
              Wir sind Familie Staub aus der Schweiz. Chalet Bambi liegt uns am Herzen –
              als Ort der Begegnung, der Erholung und der Freude an der Natur. Wir freuen
              uns, diesen besonderen Ort mit Ihnen zu teilen.
            </p>
          </div>

          {/* Foto-Grid Familie */}
          <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3 mb-16">
            {[
              { src: '/images/familie/IMG_3074.jpg', alt: 'Familie Staub' },
              { src: '/images/familie/IMG_3077.jpg', alt: 'Familie Staub' },
              { src: '/images/familie/IMG_3084.jpg', alt: 'Familie Staub' },
              { src: '/images/familie/IMG_3087.jpg', alt: 'Familie Staub' },
              { src: '/images/familie/IMG_3798.JPG', alt: 'Familie Staub' },
              { src: '/images/familie/IMG_3807.JPG', alt: 'Familie Staub' },
              { src: '/images/familie/BCTH5054.jpeg', alt: 'Familie Staub' },
              { src: '/images/familie/FPAJ0093.jpeg', alt: 'Familie Staub' },
              { src: '/images/familie/GYNY5816.jpeg', alt: 'Familie Staub' },
              { src: '/images/familie/NGUQ2505.jpeg', alt: 'Familie Staub' },
              { src: '/images/familie/SEQN5232.jpeg', alt: 'Familie Staub' },
              { src: '/images/familie/SQMS2579.jpeg', alt: 'Familie Staub' },
            ].map(({ src, alt }, i) => (
              <div key={i} className="img-hover overflow-hidden aspect-square">
                <img
                  src={src}
                  alt={alt}
                  className="w-full h-full object-cover"
                />
              </div>
            ))}
          </div>

        </div>
      </section>


</>
  )
}
