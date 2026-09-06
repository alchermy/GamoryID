import { StrictMode } from "react";
import { createRoot } from "react-dom/client";
import { BrowserRouter } from "react-router-dom";
import "./index.css";
import App from "./App.tsx";
import { initAnalytics } from "./shared/analytics.ts";

const rootElement = document.getElementById("root");

if (!rootElement) {
  throw new Error("GamoryID root element was not found");
}

initAnalytics();

createRoot(rootElement).render(
  <StrictMode>
    <BrowserRouter>
      <App />
    </BrowserRouter>
  </StrictMode>,
);
