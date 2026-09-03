import { Navigate, Route, Routes } from "react-router-dom";
import "./App.css";
import { LandingPage } from "./features/landing/LandingPage";
import { LegalPage } from "./features/legal/LegalPage";
import { BrowsePage } from "./features/storefront/BrowsePage";
import { StorefrontItemPage } from "./features/storefront/StorefrontItemPage";
import { StorefrontPage } from "./features/storefront/StorefrontPage";

export default function App() {
  return (
    <Routes>
      <Route path="/" element={<LandingPage />} />
      <Route path="/terms" element={<LegalPage kind="terms" />} />
      <Route path="/privacy" element={<LegalPage kind="privacy" />} />
      <Route path="/browse" element={<BrowsePage />} />
      <Route path="/s/:shopSlug" element={<StorefrontPage />} />
      <Route path="/s/:shopSlug/:tag" element={<StorefrontItemPage />} />
      <Route path="*" element={<Navigate to="/" replace />} />
    </Routes>
  );
}
