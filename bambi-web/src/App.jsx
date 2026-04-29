import { BrowserRouter, Routes, Route, useLocation } from 'react-router-dom'
import { useEffect } from 'react'
import Header from './components/Header'
import Footer from './components/Footer'
import Home from './pages/Home'
import Bambi from './pages/Bambi'
import Lage from './pages/Lage'
import UeberUns from './pages/UeberUns'
import Gaestebuch from './pages/Gaestebuch'

function ScrollToTop() {
  const { pathname } = useLocation()
  useEffect(() => { window.scrollTo(0, 0) }, [pathname])
  return null
}

function Layout() {
  return (
    <div className="min-h-screen bg-linen flex flex-col">
      <Header />
      <main className="flex-1">
        <ScrollToTop />
        <Routes>
          <Route path="/" element={<Home />} />
          <Route path="/bambi" element={<Bambi />} />
          <Route path="/lage" element={<Lage />} />
          <Route path="/ueber-uns" element={<UeberUns />} />
          <Route path="/gaestebuch" element={<Gaestebuch />} />
        </Routes>
      </main>
      <Footer />
    </div>
  )
}

export default function App() {
  return (
    <BrowserRouter>
      <Layout />
    </BrowserRouter>
  )
}
