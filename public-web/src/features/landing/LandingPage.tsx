import { FeaturesSection, WorkflowSection } from "./FeatureSections";
import { HeroSection } from "./HeroSection";
import { BackToTop } from "./Motion";
import { PricingSection } from "./PricingSection";
import { SiteFooter } from "./SiteFooter";
import { SiteHeader } from "./SiteHeader";

export function LandingPage() {
  return (
    <div className="site">
      <div className="ambient-background" aria-hidden="true">
        <span className="ambient-orb ambient-orb-one" />
        <span className="ambient-orb ambient-orb-two" />
        <span className="ambient-grid" />
      </div>
      <SiteHeader />
      <main id="top">
        <HeroSection />
        <FeaturesSection />
        <WorkflowSection />
        <PricingSection />
      </main>
      <SiteFooter />
      <BackToTop />
    </div>
  );
}
