import { useState } from "react";
import { Camera, Clock, Calendar, FileText, Palmtree, DollarSign, User, LogOut } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { toast } from "sonner";
import FaceRecognitionModal from "@/components/FaceRecognitionModal";

const EmployeeDashboard = () => {
  const [showFaceRecognition, setShowFaceRecognition] = useState(false);
  const [isCheckedIn, setIsCheckedIn] = useState(false);
  const [checkInTime, setCheckInTime] = useState<string | null>(null);
  const [checkOutTime, setCheckOutTime] = useState<string | null>(null);

  const handleCheckIn = () => {
    setShowFaceRecognition(true);
  };

  const handleCheckOut = () => {
    setShowFaceRecognition(true);
  };

  const handleFaceRecognized = (recognized: boolean) => {
    if (recognized) {
      const currentTime = new Date().toLocaleTimeString("en-US", {
        hour: "2-digit",
        minute: "2-digit",
      });

      if (!isCheckedIn) {
        setIsCheckedIn(true);
        setCheckInTime(currentTime);
        toast.success(`✅ Checked In at ${currentTime}`);
      } else {
        setCheckOutTime(currentTime);
        toast.success(`✅ Checked Out at ${currentTime}`);
      }
    }
    setShowFaceRecognition(false);
  };

  const features = [
    { icon: Calendar, label: "Attendance List", color: "text-primary" },
    { icon: User, label: "Profile", color: "text-info" },
    { icon: FileText, label: "Apply for Leave", color: "text-warning" },
    { icon: FileText, label: "Leave Details", color: "text-accent" },
    { icon: Palmtree, label: "Holiday List", color: "text-success" },
    { icon: DollarSign, label: "Salary Slip", color: "text-primary" },
  ];

  return (
    <div className="min-h-screen bg-background">
      {/* Header */}
      <header className="bg-card border-b border-border shadow-soft">
        <div className="container mx-auto px-4 py-4 flex items-center justify-between">
          <div className="flex items-center gap-3">
            <div className="w-10 h-10 rounded-full bg-primary/10 flex items-center justify-center">
              <User className="w-6 h-6 text-primary" />
            </div>
            <div>
              <h1 className="text-xl font-bold text-foreground">Employee Portal</h1>
              <p className="text-sm text-muted-foreground">Zaphira Organic Farms</p>
            </div>
          </div>
          <Button variant="ghost" size="sm">
            <LogOut className="w-4 h-4 mr-2" />
            Logout
          </Button>
        </div>
      </header>

      <main className="container mx-auto px-4 py-8 max-w-6xl">
        {/* Profile Card */}
        <Card className="p-6 mb-6 shadow-medium">
          <div className="flex items-center gap-4 mb-6">
            <div className="w-20 h-20 rounded-full bg-primary/20 flex items-center justify-center">
              <User className="w-10 h-10 text-primary" />
            </div>
            <div>
              <h2 className="text-2xl font-bold text-foreground">Sayed Saad Husain</h2>
              <p className="text-muted-foreground">Digital Marketing Manager</p>
              <Badge variant="secondary" className="mt-1">
                Active
              </Badge>
            </div>
          </div>

          {/* Check In/Out Buttons */}
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
            <Button
              size="lg"
              className="h-16 text-lg font-semibold"
              onClick={handleCheckIn}
              disabled={isCheckedIn}
            >
              <Camera className="w-6 h-6 mr-3" />
              Check In
            </Button>
            <Button
              size="lg"
              variant="outline"
              className="h-16 text-lg font-semibold"
              onClick={handleCheckOut}
              disabled={!isCheckedIn || !!checkOutTime}
            >
              <Camera className="w-6 h-6 mr-3" />
              Check Out
            </Button>
          </div>

          {/* Today's Status */}
          <div className="grid grid-cols-1 md:grid-cols-3 gap-4 p-4 bg-muted/50 rounded-lg">
            <div>
              <p className="text-sm text-muted-foreground mb-1">Check-In Time</p>
              <p className="text-lg font-semibold text-foreground flex items-center gap-2">
                <Clock className="w-4 h-4 text-success" />
                {checkInTime || "--:--"}
              </p>
            </div>
            <div>
              <p className="text-sm text-muted-foreground mb-1">Check-Out Time</p>
              <p className="text-lg font-semibold text-foreground flex items-center gap-2">
                <Clock className="w-4 h-4 text-warning" />
                {checkOutTime || "--:--"}
              </p>
            </div>
            <div>
              <p className="text-sm text-muted-foreground mb-1">Status</p>
              <Badge variant={isCheckedIn ? "default" : "secondary"} className="text-sm">
                {isCheckedIn ? (checkOutTime ? "Completed" : "Present") : "Absent"}
              </Badge>
            </div>
          </div>
        </Card>

        {/* Features Grid */}
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
      </main>

      {/* Face Recognition Modal */}
      {showFaceRecognition && (
        <FaceRecognitionModal
          isOpen={showFaceRecognition}
          onClose={() => setShowFaceRecognition(false)}
          onFaceRecognized={handleFaceRecognized}
        />
      )}
    </div>
  );
};

export default EmployeeDashboard;
