export default function UeberUns() {
  return (
    <>
      {/* ── HERO ── */}
      <section className="relative h-[60vh] min-h-[400px] flex items-end">
        <div className="absolute inset-0">
          <img
            src="/images/IMG_5730.jpeg"
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

      {/* ── GESCHICHTE ── */}
      <section id="geschichte" className="bg-linen py-24 px-6">
        <div className="max-w-7xl mx-auto grid grid-cols-1 lg:grid-cols-2 gap-16 items-start">
          <div>
            <p className="section-label">Die Geschichte</p>
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
                Was als Rückzugsort für die eigene Familie begann, wurde über die Jahrzehnte
                zu einem Ort, an dem viele Generationen unvergessliche Momente erlebt haben.
                Ski-Ferien mit Kindern, ruhige Sommertage im Garten, gesellige Fondue-Abende
                mit Freunden – Chalet Bambi war immer da.
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

          {/* Grosseltern Fotos */}
          <div id="familie" className="flex flex-col gap-4">
            {/* Hauptbild */}
            <div className="img-hover shadow-lg overflow-hidden">
              <img
                src="/images/bde362e6-58ad-4b12-b5d7-4fb0a39f899e.jpeg"
                alt="Die Grosseltern – Erbauer des Chalet Bambi"
                className="w-full h-96 object-cover object-center"
              />
            </div>
            {/* Zwei Bilder nebeneinander */}
            <div className="grid grid-cols-2 gap-4">
              <div className="img-hover shadow overflow-hidden">
                <img
                  src="/images/d191f743-8d2c-4737-a010-666e22d1aef6.jpeg"
                  alt="Grossvater beim Abendessen im Chalet-Garten"
                  className="w-full h-64 object-cover object-[center_20%]"
                />
              </div>
              <div className="img-hover shadow overflow-hidden">
                <img
                  src="/images/bb3ab447-68f9-40c3-b53e-089bc2857c40.jpeg"
                  alt="Die Grosseltern vor dem Chalet"
                  className="w-full h-64 object-cover object-[center_15%]"
                />
              </div>
            </div>
            <p className="font-sans text-xs text-stone text-center tracking-wide italic">
              Die Grosseltern – Erbauer und Seele des Chalet Bambi
            </p>
          </div>
        </div>
      </section>

      {/* ── WERTE / PHILOSOPHIE ── */}
      <section id="werte" className="bg-cream py-24 px-6">
        <div className="max-w-7xl mx-auto">
          <div className="mb-16 text-center">
            <p className="section-label">Unsere Werte</p>
            <div className="divider mx-auto mb-6" />
            <h2 className="section-heading max-w-2xl mx-auto">
              Was uns am Herzen liegt
            </h2>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-3 gap-10">
            {[
              {
                titel: 'Nachhaltigkeit',
                text: 'Wir pflegen das Haus und die Natur um uns herum mit Respekt. Die UNESCO Biosphäre Entlebuch ist unser Zuhause – und das möchten wir bewahren.',
              },
              {
                titel: 'Persönlichkeit',
                text: 'Kein steriles Hotel, sondern echtes Familienflair. Jeder Winkel von Chalet Bambi trägt eine Geschichte und lädt ein, eigene zu schreiben.',
              },
              {
                titel: 'Herzlichkeit',
                text: 'Wir stehen gerne mit Rat und Tat zur Seite – ob Wandertipp, Restaurantempfehlung oder die beste Skipiste für Anfänger.',
              },
            ].map(({ titel, text }) => (
              <div key={titel} className="border-t border-stone-light/60 pt-8">
                <h3 className="font-serif text-xl font-light text-charcoal mb-4">{titel}</h3>
                <p className="font-sans text-sm text-charcoal-light leading-relaxed">{text}</p>
              </div>
            ))}
          </div>
        </div>
      </section>

    </>
  )
}
