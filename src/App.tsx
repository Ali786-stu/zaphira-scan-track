import { Toaster } from "@/components/ui/toaster";
import { Toaster as Sonner } from "@/components/ui/sonner";
import { TooltipProvider } from "@/components/ui/tooltip";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { BrowserRouter, Routes, Route } from "react-router-dom";
import Index from "./pages/Index";
import Login from "./pages/Login";
import EmployeeDashboard from "./pages/EmployeeDashboard";
import AdminDashboard from "./pages/AdminDashboard";
import NotFound from "./pages/NotFound";
import EmployeeLayout from "./components/EmployeeLayout";
import AttendanceList from "./pages/employee/AttendanceList";
import Profile from "./pages/employee/Profile";
import ApplyLeave from "./pages/employee/ApplyLeave";
import LeaveDetails from "./pages/employee/LeaveDetails";
import HolidayList from "./pages/employee/HolidayList";
import SalarySlip from "./pages/employee/SalarySlip";

const queryClient = new QueryClient();

const App = () => (
  <QueryClientProvider client={queryClient}>
    <TooltipProvider>
      <Toaster />
      <Sonner />
      <BrowserRouter>
        <Routes>
          <Route path="/" element={<Index />} />
          <Route path="/login" element={<Login />} />
          <Route path="/employee" element={<EmployeeLayout />}>
            <Route index element={<EmployeeDashboard />} />
            <Route path="attendance" element={<AttendanceList />} />
            <Route path="profile" element={<Profile />} />
            <Route path="apply-leave" element={<ApplyLeave />} />
            <Route path="leave-details" element={<LeaveDetails />} />
            <Route path="holidays" element={<HolidayList />} />
            <Route path="salary" element={<SalarySlip />} />
          </Route>
          <Route path="/admin" element={<AdminDashboard />} />
          {/* ADD ALL CUSTOM ROUTES ABOVE THE CATCH-ALL "*" ROUTE */}
          <Route path="*" element={<NotFound />} />
        </Routes>
      </BrowserRouter>
    </TooltipProvider>
  </QueryClientProvider>
);

export default App;
