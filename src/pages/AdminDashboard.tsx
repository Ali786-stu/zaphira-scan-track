import { Users, UserCheck, UserX, ClipboardList, Calendar, Palmtree, Settings, FileText, BarChart3, LogOut } from "lucide-react";
import { Card } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";

const AdminDashboard = () => {
  const stats = [
    { label: "Total Employees", value: "23", icon: Users, color: "text-primary" },
    { label: "Active", value: "16", icon: UserCheck, color: "text-success" },
    { label: "Inactive", value: "7", icon: UserX, color: "text-muted-foreground" },
  ];

  const features = [
    { icon: Users, label: "Employee List", color: "text-primary" },
    { icon: ClipboardList, label: "Attendance Report", color: "text-info" },
    { icon: BarChart3, label: "Analytics", color: "text-success" },
    { icon: FileText, label: "Leave Management", color: "text-warning" },
    { icon: Palmtree, label: "Holiday Management", color: "text-accent" },
    { icon: Settings, label: "System Settings", color: "text-muted-foreground" },
  ];

  const recentAttendance = [
    { name: "Shayan Abdul Jishan", status: "Present", time: "09:02 AM", role: "CEO" },
    { name: "Roli Sharma", status: "Present", time: "08:58 AM", role: "HR" },
    { name: "Sayed Saad Husain", status: "Present", time: "09:15 AM", role: "Marketing" },
    { name: "Ramesh Kumar", status: "Late", time: "09:45 AM", role: "Operations" },
  ];

  return (
    <div className="min-h-screen bg-background">
      {/* Header */}
      <header className="bg-card border-b border-border shadow-soft">
        <div className="container mx-auto px-4 py-4 flex items-center justify-between">
          <div className="flex items-center gap-3">
            <div className="w-10 h-10 rounded-full bg-primary/10 flex items-center justify-center">
              <Users className="w-6 h-6 text-primary" />
            </div>
            <div>
              <h1 className="text-xl font-bold text-foreground">Admin Dashboard</h1>
              <p className="text-sm text-muted-foreground">Zaphira Organic Farms</p>
            </div>
          </div>
          <Button variant="ghost" size="sm">
            <LogOut className="w-4 h-4 mr-2" />
            Logout
          </Button>
        </div>
      </header>

      <main className="container mx-auto px-4 py-8 max-w-7xl">
        {/* Company Overview */}
        <Card className="p-6 mb-6 shadow-medium">
          <h2 className="text-2xl font-bold text-foreground mb-4">
            Company Overview
          </h2>
          <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
            {stats.map((stat, index) => (
              <div key={index} className="flex items-center gap-4">
                <div className="w-14 h-14 rounded-full bg-secondary flex items-center justify-center">
                  <stat.icon className={`w-7 h-7 ${stat.color}`} />
                </div>
                <div>
                  <p className="text-sm text-muted-foreground">{stat.label}</p>
                  <p className="text-3xl font-bold text-foreground">{stat.value}</p>
                </div>
              </div>
            ))}
          </div>
        </Card>

        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
          {/* Features Grid */}
          <div className="lg:col-span-2">
            <h3 className="text-lg font-semibold text-foreground mb-4">
              Management Tools
            </h3>
            <div className="grid grid-cols-2 md:grid-cols-3 gap-4">
              {features.map((feature, index) => (
                <Card
                  key={index}
                  className="p-6 hover:shadow-medium transition-all duration-300 cursor-pointer group"
                >
                  <div className="text-center">
                    <div className="inline-flex items-center justify-center w-14 h-14 rounded-full bg-secondary mb-3 group-hover:scale-110 transition-transform">
                      <feature.icon className={`w-7 h-7 ${feature.color}`} />
                    </div>
                    <h3 className="text-sm font-semibold text-foreground">
                      {feature.label}
                    </h3>
                  </div>
                </Card>
              ))}
            </div>
          </div>

          {/* Recent Attendance */}
          <div>
            <h3 className="text-lg font-semibold text-foreground mb-4">
              Today's Attendance
            </h3>
            <Card className="p-4 shadow-medium">
              <div className="space-y-3">
                {recentAttendance.map((entry, index) => (
                  <div
                    key={index}
                    className="flex items-center justify-between p-3 bg-muted/50 rounded-lg"
                  >
                    <div className="flex-1">
                      <p className="font-semibold text-sm text-foreground">
                        {entry.name}
                      </p>
                      <p className="text-xs text-muted-foreground">{entry.role}</p>
                    </div>
                    <div className="text-right">
                      <Badge
                        variant={entry.status === "Present" ? "default" : "destructive"}
                        className="text-xs mb-1"
                      >
                        {entry.status}
                      </Badge>
                      <p className="text-xs text-muted-foreground">{entry.time}</p>
                    </div>
                  </div>
                ))}
              </div>
              <Button variant="outline" className="w-full mt-4">
                View Full Report
              </Button>
            </Card>
          </div>
        </div>

        {/* Quick Actions */}
        <Card className="p-6 shadow-medium">
          <h3 className="text-lg font-semibold text-foreground mb-4">
            Quick Actions
          </h3>
          <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
            <Button variant="default" className="h-12">
              <Calendar className="w-4 h-4 mr-2" />
              Mark Holiday
            </Button>
            <Button variant="outline" className="h-12">
              <FileText className="w-4 h-4 mr-2" />
              Generate Report
            </Button>
            <Button variant="outline" className="h-12">
              <Users className="w-4 h-4 mr-2" />
              Add Employee
            </Button>
          </div>
        </Card>
      </main>
    </div>
  );
};

export default AdminDashboard;
