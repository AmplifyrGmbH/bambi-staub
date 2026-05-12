import { useState, useEffect, useRef } from 'react'
import { NavLink, Link, useNavigate } from 'react-router-dom'

const BOOKING_URL = 'https://booking.haus-bambi.ch'

const navLinks = [
  {
    to: '/bambi',
    label: 'Bambi',
    sub: [
      { label: 'Räumlichkeiten', hash: '#raeumlichkeiten' },
      { label: 'Ausstattung', hash: '#ausstattung' },
      { label: 'Hausordnung', hash: '#hausordnung' },
    ],
  },
  {
    to: '/lage',
    label: 'Umgebung & Aktivitäten',
    sub: [
      { label: 'UNESCO Biosphäre', hash: '#biosphaere' },
      { label: 'Aktivitäten', hash: '#winter' },
      { label: 'Kulinarik', hash: '#kulinarik' },
      { label: 'Anreise', hash: '#anreise' },
    ],
  },
  { to: '/ueber-uns', label: 'Über uns' },
  { to: '/gaestebuch', label: 'Gästebuch' },
]

function DropdownItem({ link, scrolled, closeAll }) {
  const [open, setOpen] = useState(false)
  const ref = useRef(null)
  const closeTimer = useRef(null)
  const navigate = useNavigate()

  useEffect(() => {
    const handler = (e) => { if (ref.current && !ref.current.contains(e.target)) setOpen(false) }
    document.addEventListener('mousedown', handler)
    return () => document.removeEventListener('mousedown', handler)
  }, [])

  const handleMouseEnter = () => {
    clearTimeout(closeTimer.current)
    setOpen(true)
  }

  const handleMouseLeave = () => {
    closeTimer.current = setTimeout(() => setOpen(false), 120)
  }

  const handleSubClick = (hash) => {
    setOpen(false)
    closeAll()
    navigate(link.to + hash)
    setTimeout(() => {
      const el = document.querySelector(hash)
      if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' })
    }, 100)
  }

  if (!link.sub) {
    return (
      <NavLink
        to={link.to}
        onClick={closeAll}
        className={({ isActive }) =>
          `font-sans text-sm tracking-wide transition-colors duration-200 ${
            scrolled
              ? isActive ? 'text-forest font-medium' : 'text-charcoal-light hover:text-forest'
              : isActive ? 'text-linen font-medium' : 'text-linen/80 hover:text-linen'
          }`
        }
      >
        {link.label}
      </NavLink>
    )
  }

  return (
    <div ref={ref} className="relative" onMouseEnter={handleMouseEnter} onMouseLeave={handleMouseLeave}>
      <NavLink
        to={link.to}
        onClick={closeAll}
        className={({ isActive }) =>
          `flex items-center gap-1 font-sans text-sm tracking-wide transition-colors duration-200 ${
            scrolled
              ? isActive ? 'text-forest font-medium' : 'text-charcoal-light hover:text-forest'
              : isActive ? 'text-linen font-medium' : 'text-linen/80 hover:text-linen'
          }`
        }
      >
        {link.label}
        <svg viewBox="0 0 12 12" fill="none" stroke="currentColor" strokeWidth="1.5"
          className={`w-3 h-3 transition-transform duration-200 ${open ? 'rotate-180' : ''}`}>
          <path strokeLinecap="round" strokeLinejoin="round" d="M2 4l4 4 4-4" />
        </svg>
      </NavLink>

      {/* Dropdown Panel */}
      <div className={`absolute top-full left-0 mt-3 w-52 glass-nav shadow-lg transition-all duration-200 overflow-hidden ${
        open ? 'opacity-100 translate-y-0 pointer-events-auto' : 'opacity-0 -translate-y-1 pointer-events-none'
      }`}>
        {link.sub.map(({ label, hash }) => (
          <button
            key={hash}
            onClick={() => handleSubClick(hash)}
            className="w-full text-left px-5 py-3 font-sans text-sm text-charcoal-light hover:text-forest hover:bg-linen-dark transition-colors border-b border-stone-light/20 last:border-0"
          >
            {label}
          </button>
        ))}
      </div>
    </div>
  )
}

export default function Header() {
  const [menuOpen, setMenuOpen] = useState(false)
  const [openMobileSub, setOpenMobileSub] = useState(null)
  const [scrolled, setScrolled] = useState(false)
  const navigate = useNavigate()

  useEffect(() => {
    const onScroll = () => setScrolled(window.scrollY > 20)
    window.addEventListener('scroll', onScroll)
    return () => window.removeEventListener('scroll', onScroll)
  }, [])

  const handleMobileSubClick = (to, hash) => {
    setMenuOpen(false)
    setOpenMobileSub(null)
    navigate(to + hash)
    setTimeout(() => {
      const el = document.querySelector(hash)
      if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' })
    }, 100)
  }

  return (
    <header className={`fixed top-0 left-0 right-0 z-50 transition-all duration-300 ${scrolled ? 'glass-nav shadow-sm py-3' : 'bg-transparent py-5'}`}>
      <div className="max-w-7xl mx-auto px-6 flex items-center justify-between">
        {/* Logo */}
        <Link to="/" className="flex flex-col leading-none" onClick={() => setMenuOpen(false)}>
          <span className={`font-serif text-xl font-light tracking-wide transition-colors duration-300 ${scrolled ? 'text-charcoal' : 'text-linen'}`}>
            Chalet Bambi
          </span>
          <span className={`font-sans text-xs tracking-widest uppercase transition-colors duration-300 ${scrolled ? 'text-stone' : 'text-linen/70'}`}>
            Sörenberg
          </span>
        </Link>

        {/* Desktop Nav */}
        <nav className="hidden md:flex items-center gap-8">
          {navLinks.map((link) => (
            <DropdownItem key={link.to} link={link} scrolled={scrolled} closeAll={() => {}} />
          ))}
        </nav>

        {/* CTA + Hamburger */}
        <div className="flex items-center gap-4">
          <a
            href={BOOKING_URL}
            target="_blank"
            rel="noopener noreferrer"
            className={`hidden md:inline-block px-6 py-2.5 font-sans text-sm font-medium tracking-wider uppercase transition-all duration-300 ${
              scrolled ? 'bg-forest text-linen hover:bg-forest-light' : 'bg-linen/15 border border-linen/60 text-linen hover:bg-linen/25'
            }`}
          >
            Jetzt buchen
          </a>

          <button
            onClick={() => setMenuOpen(!menuOpen)}
            className={`md:hidden flex flex-col gap-1.5 p-2 transition-colors ${scrolled ? 'text-charcoal' : 'text-linen'}`}
            aria-label="Menü öffnen"
          >
            <span className={`block w-6 h-px bg-current transition-all duration-300 ${menuOpen ? 'rotate-45 translate-y-2.5' : ''}`} />
            <span className={`block w-6 h-px bg-current transition-all duration-300 ${menuOpen ? 'opacity-0' : ''}`} />
            <span className={`block w-6 h-px bg-current transition-all duration-300 ${menuOpen ? '-rotate-45 -translate-y-2' : ''}`} />
          </button>
        </div>
      </div>

      {/* Mobile Menu */}
      <div className={`md:hidden glass-nav transition-all duration-300 overflow-hidden ${menuOpen ? 'max-h-screen py-6' : 'max-h-0'}`}>
        <nav className="flex flex-col px-6">
          {navLinks.map((link) => (
            <div key={link.to}>
              <div className="flex items-center justify-between border-b border-stone-light/30">
                <NavLink
                  to={link.to}
                  onClick={() => setMenuOpen(false)}
                  className={({ isActive }) =>
                    `flex-1 py-3 font-sans text-sm tracking-wide transition-colors ${isActive ? 'text-forest font-medium' : 'text-charcoal-light'}`
                  }
                >
                  {link.label}
                </NavLink>
                {link.sub && (
                  <button
                    onClick={() => setOpenMobileSub(openMobileSub === link.to ? null : link.to)}
                    className="p-2 text-stone"
                    aria-label="Untermenü"
                  >
                    <svg viewBox="0 0 12 12" fill="none" stroke="currentColor" strokeWidth="1.5" className={`w-3 h-3 transition-transform ${openMobileSub === link.to ? 'rotate-180' : ''}`}>
                      <path strokeLinecap="round" strokeLinejoin="round" d="M2 4l4 4 4-4" />
                    </svg>
                  </button>
                )}
              </div>

              {/* Mobile Sub Items */}
              {link.sub && openMobileSub === link.to && (
                <div className="pl-4 flex flex-col">
                  {link.sub.map(({ label, hash }) => (
                    <button
                      key={hash}
                      onClick={() => handleMobileSubClick(link.to, hash)}
                      className="py-2.5 text-left font-sans text-xs text-forest-muted border-b border-stone-light/20 last:border-0 hover:text-forest transition-colors"
                    >
                      {label}
                    </button>
                  ))}
                </div>
              )}
            </div>
          ))}

          <a
            href={BOOKING_URL}
            target="_blank"
            rel="noopener noreferrer"
            className="mt-4 text-center py-3.5 bg-forest text-linen font-sans text-sm font-medium tracking-wider uppercase"
          >
            Jetzt buchen
          </a>
        </nav>
      </div>
    </header>
  )
}
