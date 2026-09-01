import {
  BrowserRouter,
  MemoryRouter,
  Route,
  Routes,
  useParams,
} from "react-router-dom";
import {
  AuthGate,
  AuthScreen,
  InviteScreen,
  StatePage,
  VerifyEmailScreen,
} from "../features/auth/auth-pages";
import { MerchantApp } from "../features/merchant/MerchantApp";

const merchantPaths = [
  "/",
  "/inventory",
  "/sales",
  "/customers",
  "/imports",
  "/team",
  "/billing",
  "/transactions",
  "/discord",
  "/settings",
];

function InviteRoute() {
  const { token } = useParams();
  return token ? (
    <InviteScreen token={token} />
  ) : (
    <StatePage code="404" title="ไม่พบคำเชิญ" text="ลิงก์คำเชิญนี้ไม่ถูกต้อง" />
  );
}

function AppRoutes() {
  return (
    <Routes>
      <Route path="/login" element={<AuthScreen mode="login" />} />
      <Route path="/register" element={<AuthScreen mode="register" />} />
      <Route path="/invite/:token" element={<InviteRoute />} />
      <Route
        path="/403"
        element={
          <StatePage
            code="403"
            title="ไม่มีสิทธิ์เข้าถึง"
            text="บัญชีนี้ไม่มีสิทธิ์เปิดหน้านี้ กรุณาติดต่อเจ้าของร้าน"
          />
        }
      />
      <Route element={<AuthGate />}>
        <Route path="/verify-email" element={<VerifyEmailScreen />} />
        <Route element={<MerchantApp />}>
          {merchantPaths.map((path) => (
            <Route key={path} path={path} element={null} />
          ))}
          <Route path="/sales/:saleId" element={null} />
        </Route>
      </Route>
      <Route
        path="*"
        element={
          <StatePage
            code="404"
            title="ไม่พบหน้าที่ต้องการ"
            text="ลิงก์นี้อาจถูกย้ายหรือไม่มีอยู่แล้ว"
          />
        }
      />
    </Routes>
  );
}

export function AppRouter() {
  const Router = import.meta.env.MODE === "test" ? MemoryRouter : BrowserRouter;
  return (
    <Router>
      <AppRoutes />
    </Router>
  );
}
