import { FeaturesSection, WorkflowSection } from "./FeatureSections";
import { HeroSection } from "./HeroSection";
import { PricingSection } from "./PricingSection";
import { SiteFooter } from "./SiteFooter";
import { SiteHeader } from "./SiteHeader";

export function LandingPage() {
  return (
    <div className="site">
      <SiteHeader />
      <main id="top">
        <HeroSection />
        <FeaturesSection />
        <WorkflowSection />
        <PricingSection />
      </main>
      <SiteFooter />
    </div>
  );
}
