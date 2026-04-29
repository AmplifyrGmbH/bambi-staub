import { useState } from 'react'

const initialReviews = [
  {
    id: 1,
    name: 'Familie Müller',
    datum: 'Februar 2024',
    sterne: 5,
    text: 'Wir haben Weihnachten und Neujahr in Chalet Bambi verbracht – absolut traumhaft! Das Arvenholz-Ambiente ist genau so gemütlich wie auf den Fotos. Die Kinder haben täglich auf der Piste gestanden, abends gab es Fondue. Wir kommen definitiv wieder!',
    aufenthalt: 'Winter – 10 Tage',
  },
  {
    id: 2,
    name: 'Peter & Claudia W.',
    datum: 'Juli 2023',
    sterne: 5,
    text: 'Ein wunderschöner Sommerurlaub in der Biosphäre Entlebuch. Das Haus ist top ausgestattet, der Garten ein Traum. Wir haben täglich gewandert und am Abend auf der Terrasse die Stille genossen. Familie Staub war immer erreichbar und sehr hilfsbereit.',
    aufenthalt: 'Sommer – 7 Tage',
  },
  {
    id: 3,
    name: 'Die Zürcher Grossfamilie',
    datum: 'Januar 2023',
    sterne: 5,
    text: 'Wir waren zu acht – drei Generationen! Das Haus hat uns alle aufgenommen. Die Pistenanbindung ist unschlagbar, der Skikeller sehr praktisch. Das Haus hat Charakter und Geschichte, das spürt man in jedem Winkel.',
    aufenthalt: 'Winter – 6 Tage',
  },
]

function StarRating({ count }) {
  return (
    <div className="flex gap-1">
      {[1, 2, 3, 4, 5].map((i) => (
        <svg
          key={i}
          viewBox="0 0 16 16"
          className={`w-4 h-4 ${i <= count ? 'fill-terracotta' : 'fill-stone-light'}`}
        >
          <path d="M8 1l1.85 3.75 4.15.6-3 2.93.7 4.1L8 10.4l-3.7 1.98.7-4.1-3-2.93 4.15-.6L8 1z" />
        </svg>
      ))}
    </div>
  )
}

function InteractiveStar({ value, hover, onHover, onClick }) {
  return (
    <button type="button" onMouseEnter={() => onHover(value)} onMouseLeave={() => onHover(0)} onClick={() => onClick(value)} className="p-0.5">
      <svg viewBox="0 0 16 16" className={`w-6 h-6 transition-colors ${value <= hover ? 'fill-terracotta' : 'fill-stone-light'}`}>
        <path d="M8 1l1.85 3.75 4.15.6-3 2.93.7 4.1L8 10.4l-3.7 1.98.7-4.1-3-2.93 4.15-.6L8 1z" />
      </svg>
    </button>
  )
}

export default function Gaestebuch() {
  const [reviews, setReviews] = useState(initialReviews)
  const [form, setForm] = useState({ name: '', aufenthalt: '', sterne: 0, text: '' })
  const [hoverStern, setHoverStern] = useState(0)
  const [submitted, setSubmitted] = useState(false)
  const [errors, setErrors] = useState({})

  const validate = () => {
    const e = {}
    if (!form.name.trim()) e.name = 'Bitte geben Sie Ihren Namen an.'
    if (form.sterne === 0) e.sterne = 'Bitte wählen Sie eine Bewertung.'
    if (form.text.trim().length < 20) e.text = 'Bitte schreiben Sie mindestens 20 Zeichen.'
    return e
  }

  const handleSubmit = (e) => {
    e.preventDefault()
    const errs = validate()
    if (Object.keys(errs).length > 0) { setErrors(errs); return }

    const newReview = {
      id: Date.now(),
      name: form.name,
      aufenthalt: form.aufenthalt || 'Aufenthalt in Sörenberg',
      sterne: form.sterne,
      text: form.text,
      datum: new Date().toLocaleDateString('de-CH', { month: 'long', year: 'numeric' }),
    }
    setReviews([newReview, ...reviews])
    setForm({ name: '', aufenthalt: '', sterne: 0, text: '' })
    setErrors({})
    setSubmitted(true)
    setTimeout(() => setSubmitted(false), 5000)
  }

  return (
    <>
      {/* ── HERO ── */}
      <section className="relative h-[50vh] min-h-[360px] flex items-end">
        <div className="absolute inset-0">
          <img
            src="/images/IMG_5669.jpeg"
            alt="Balkon Chalet Bambi"
            className="w-full h-full object-cover"
          />
          <div className="absolute inset-0 bg-gradient-to-t from-charcoal/75 via-charcoal/20 to-transparent" />
        </div>
        <div className="relative z-10 max-w-7xl mx-auto px-6 pb-16">
          <p className="section-label text-linen/50">Stimmen unserer Gäste</p>
          <h1 className="font-serif text-5xl md:text-6xl font-light text-linen leading-tight">
            Gästebuch
          </h1>
        </div>
      </section>

      {/* ── REVIEWS ── */}
      <section className="bg-linen py-24 px-6">
        <div className="max-w-7xl mx-auto">
          <div className="mb-12">
            <p className="section-label">Erfahrungen</p>
            <div className="divider mb-6" />
            <h2 className="section-heading">Was unsere Gäste sagen</h2>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            {reviews.map(({ id, name, datum, sterne, text, aufenthalt }) => (
              <article key={id} className="flex flex-col bg-cream p-8 transition-shadow hover:shadow-md">
                <div className="flex items-start justify-between mb-4">
                  <StarRating count={sterne} />
                  <span className="font-sans text-xs text-stone tracking-wide">{datum}</span>
                </div>
                <blockquote className="font-serif text-base font-light text-charcoal leading-relaxed italic flex-1 mb-6">
                  „{text}"
                </blockquote>
                <div className="border-t border-stone-light/40 pt-5 mt-auto">
                  <p className="font-sans text-sm font-medium text-charcoal">{name}</p>
                  <p className="font-sans text-xs text-stone mt-1">{aufenthalt}</p>
                </div>
              </article>
            ))}
          </div>
        </div>
      </section>

      {/* ── FORMULAR ── */}
      <section className="bg-cream py-24 px-6">
        <div className="max-w-2xl mx-auto">
          <div className="mb-12">
            <p className="section-label">Ihre Meinung</p>
            <div className="divider mb-6" />
            <h2 className="section-heading">Erfahrung teilen</h2>
            <p className="font-sans text-base text-charcoal-light mt-4 leading-relaxed">
              Waren Sie zu Gast in Chalet Bambi? Wir freuen uns sehr über Ihre Rückmeldung!
            </p>
          </div>

          {submitted && (
            <div className="mb-8 p-5 bg-forest/10 border border-forest/20 text-forest font-sans text-sm">
              Vielen Dank für Ihre Bewertung! Sie wurde erfolgreich hinzugefügt.
            </div>
          )}

          <form onSubmit={handleSubmit} noValidate className="flex flex-col gap-6">
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-6">
              {/* Name */}
              <div>
                <label className="font-sans text-xs tracking-widest uppercase text-charcoal-light mb-2 block">
                  Name *
                </label>
                <input
                  type="text"
                  value={form.name}
                  onChange={(e) => setForm({ ...form, name: e.target.value })}
                  placeholder="Ihre Familie / Ihr Name"
                  className={`w-full px-4 py-3 bg-linen border font-sans text-sm text-charcoal outline-none transition-colors focus:border-forest ${errors.name ? 'border-terracotta' : 'border-stone-light/60'}`}
                />
                {errors.name && <p className="font-sans text-xs text-terracotta mt-1">{errors.name}</p>}
              </div>

              {/* Aufenthalt */}
              <div>
                <label className="font-sans text-xs tracking-widest uppercase text-charcoal-light mb-2 block">
                  Aufenthalt
                </label>
                <input
                  type="text"
                  value={form.aufenthalt}
                  onChange={(e) => setForm({ ...form, aufenthalt: e.target.value })}
                  placeholder="z.B. Winter – 5 Tage"
                  className="w-full px-4 py-3 bg-linen border border-stone-light/60 font-sans text-sm text-charcoal outline-none transition-colors focus:border-forest"
                />
              </div>
            </div>

            {/* Sterne */}
            <div>
              <label className="font-sans text-xs tracking-widest uppercase text-charcoal-light mb-2 block">
                Bewertung *
              </label>
              <div className="flex gap-1">
                {[1, 2, 3, 4, 5].map((v) => (
                  <InteractiveStar
                    key={v}
                    value={v}
                    hover={hoverStern || form.sterne}
                    onHover={setHoverStern}
                    onClick={(v) => setForm({ ...form, sterne: v })}
                  />
                ))}
              </div>
              {errors.sterne && <p className="font-sans text-xs text-terracotta mt-1">{errors.sterne}</p>}
            </div>

            {/* Text */}
            <div>
              <label className="font-sans text-xs tracking-widest uppercase text-charcoal-light mb-2 block">
                Ihr Erfahrungsbericht *
              </label>
              <textarea
                rows={5}
                value={form.text}
                onChange={(e) => setForm({ ...form, text: e.target.value })}
                placeholder="Erzählen Sie uns von Ihrem Aufenthalt..."
                className={`w-full px-4 py-3 bg-linen border font-sans text-sm text-charcoal outline-none transition-colors focus:border-forest resize-none ${errors.text ? 'border-terracotta' : 'border-stone-light/60'}`}
              />
              {errors.text && <p className="font-sans text-xs text-terracotta mt-1">{errors.text}</p>}
            </div>

            <button type="submit" className="btn-primary self-start">
              Bewertung abschicken
            </button>
          </form>
        </div>
      </section>
    </>
  )
}
