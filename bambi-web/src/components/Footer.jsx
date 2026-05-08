import { Link } from 'react-router-dom'

const BOOKING_URL = 'https://booking.haus-bambi.ch' // Placeholder – replace with real PMS URL

export default function Footer() {
  return (
    <footer className="bg-charcoal text-linen/70">
      {/* CTA Banner */}
      <div className="bg-forest py-16 px-6 text-center">
        <p className="section-label text-linen/50 mb-4">Bereit für Ihre Auszeit?</p>
        <h2 className="font-serif text-3xl md:text-4xl font-light text-linen mb-6">
          Chalet Bambi wartet auf Sie
        </h2>
        <a
          href={BOOKING_URL}
          target="_blank"
          rel="noopener noreferrer"
          className="inline-block px-10 py-4 bg-linen text-forest font-sans text-sm font-medium tracking-wider uppercase transition-all duration-300 hover:bg-cream"
        >
          Jetzt buchen
        </a>
      </div>

      {/* Footer Content */}
      <div className="max-w-7xl mx-auto px-6 py-16 grid grid-cols-1 md:grid-cols-3 gap-12">
        {/* Brand */}
        <div>
          <h3 className="font-serif text-xl font-light text-linen mb-2">Chalet Bambi</h3>
          <p className="font-sans text-xs tracking-widest uppercase text-linen/40 mb-4">Sörenberg, Schweiz</p>
          <p className="font-sans text-sm leading-relaxed">
            Ein Familienferienhaus mit Geschichte – direkt in der UNESCO Biosphäre Entlebuch.
          </p>
        </div>

        {/* Navigation */}
        <div>
          <p className="font-sans text-xs tracking-widest uppercase text-linen/40 mb-5">Navigation</p>
          <nav className="flex flex-col gap-2.5">
            {[
              { to: '/bambi', label: 'Das Haus' },
              { to: '/lage', label: 'Umgebung & Aktivitäten' },
              { to: '/ueber-uns', label: 'Über uns' },
              { to: '/gaestebuch', label: 'Gästebuch' },
            ].map(({ to, label }) => (
              <Link
                key={to}
                to={to}
                className="font-sans text-sm text-linen/60 hover:text-linen transition-colors"
              >
                {label}
              </Link>
            ))}
          </nav>
        </div>

        {/* Contact */}
        <div>
          <p className="font-sans text-xs tracking-widest uppercase text-linen/40 mb-5">Adresse</p>
          <address className="not-italic font-sans text-sm leading-relaxed">
            Rothornstrasse 40<br />
            6174 Sörenberg<br />
            Schweiz
          </address>
          <div className="mt-6">
            <p className="font-sans text-xs tracking-widest uppercase text-linen/40 mb-2">Kontakt</p>
            <a href="mailto:info@bambi-staub.ch" className="font-sans text-sm text-linen/60 hover:text-linen transition-colors">
              info@bambi-staub.ch
            </a>
          </div>
        </div>
      </div>

      {/* Bottom bar */}
      <div className="border-t border-linen/10 py-6 px-6 flex flex-col md:flex-row items-center justify-between gap-3 max-w-7xl mx-auto">
        <p className="font-sans text-xs text-linen/30">
          © {new Date().getFullYear()} Chalet Bambi, Familie Staub. Alle Rechte vorbehalten.
        </p>
        <p className="font-sans text-xs text-linen/30">
          Sörenberg · UNESCO Biosphäre Entlebuch
        </p>
      </div>
    </footer>
  )
}
