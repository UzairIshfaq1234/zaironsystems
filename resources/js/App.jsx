import { lazy } from 'react';
import { Route, Routes } from 'react-router-dom';
import Layout from './components/layout/Layout';
import Home from './pages/Home';

// Home ships in the main bundle; every other page loads on demand.
const Services = lazy(() => import('./pages/Services'));
const ServiceDetail = lazy(() => import('./pages/ServiceDetail'));
const Work = lazy(() => import('./pages/Work'));
const About = lazy(() => import('./pages/About'));
const Careers = lazy(() => import('./pages/Careers'));
const Insights = lazy(() => import('./pages/Insights'));
const Contact = lazy(() => import('./pages/Contact'));
const Legal = lazy(() => import('./pages/Legal'));
const NotFound = lazy(() => import('./pages/NotFound'));

// Keep in sync with resources/content/pages.json (the server uses it for meta tags and 404 status).
export default function App() {
    return (
        <Routes>
            <Route element={<Layout />}>
                <Route index element={<Home />} />
                <Route path="services" element={<Services />} />
                <Route path="services/:slug" element={<ServiceDetail />} />
                <Route path="work" element={<Work />} />
                <Route path="about" element={<About />} />
                <Route path="careers" element={<Careers />} />
                <Route path="insights" element={<Insights />} />
                <Route path="contact" element={<Contact />} />
                <Route path="privacy" element={<Legal doc="privacy" />} />
                <Route path="terms" element={<Legal doc="terms" />} />
                <Route path="*" element={<NotFound />} />
            </Route>
        </Routes>
    );
}
