import { AboutSection } from "./AboutSection";
import {
  FeaturesSection,
  SpotlightSection,
  WorkflowSection,
} from "./FeatureSections";
import { HeroSection, SpecStrip } from "./HeroSection";
import { BackToTop, ScrollProgress } from "./Motion";
import { PricingSection } from "./PricingSection";
import { SiteFooter } from "./SiteFooter";
import { SiteHeader } from "./SiteHeader";

export function LandingPage() {
  return (
    <div className="site">
      <ScrollProgress />
      <SiteHeader />
      <main id="top">
        <HeroSection />
        <SpecStrip />
        <FeaturesSection />
        <AboutSection />
        <WorkflowSection />
        <SpotlightSection />
        <PricingSection />
      </main>
      <SiteFooter />
      <BackToTop />
    </div>
  );
}
