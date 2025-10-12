import { useNavigate } from "react-router-dom";
import { Leaf, Users, Clock, Shield } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";

const Index = () => {
  const navigate = useNavigate();

  const features = [
    {
      icon: Users,
      title: "Employee Management",
      description: "Comprehensive employee database with role-based access",
    },
    {
      icon: Clock,
      title: "Real-Time Attendance",
      description: "Track check-ins and check-outs with precision",
    },
    {
      icon: Shield,
      title: "Face Recognition",
      description: "Secure biometric authentication using AI",
    },
  ];

  return (
    <div className="min-h-screen bg-gradient-to-br from-background via-secondary/30 to-background">
      {/* Hero Section */}
      <header className="container mx-auto px-4 py-8">
        <div className="flex items-center gap-3 mb-12">
          <div className="w-12 h-12 rounded-full bg-primary/10 flex items-center justify-center">
            <Leaf className="w-7 h-7 text-primary" />
          </div>
          <div>
            <h1 className="text-2xl font-bold text-foreground">
              Zaphira Organic Farms
            </h1>
            <p className="text-sm text-muted-foreground">
              Smart Attendance Management
            </p>
          </div>
        </div>

        <div className="text-center max-w-3xl mx-auto mb-12 animate-fade-in">
          <h2 className="text-4xl md:text-5xl font-bold text-foreground mb-4">
            Face Detection Attendance Management System
          </h2>
          <p className="text-xl text-muted-foreground mb-8">
            Modern, secure, and efficient attendance tracking powered by AI
          </p>
          <Button
            size="lg"
            onClick={() => navigate("/login")}
            className="h-14 px-8 text-lg font-semibold"
          >
            Access Portal
          </Button>
        </div>
      </header>

      {/* Features */}
      <section className="container mx-auto px-4 py-12">
        <div className="grid grid-cols-1 md:grid-cols-3 gap-6 max-w-5xl mx-auto">
          {features.map((feature, index) => (
            <Card
              key={index}
              className="p-6 text-center shadow-medium hover:shadow-large transition-all duration-300 animate-slide-up"
              style={{ animationDelay: `${index * 100}ms` }}
            >
              <div className="inline-flex items-center justify-center w-16 h-16 rounded-full bg-primary/10 mb-4">
                <feature.icon className="w-8 h-8 text-primary" />
              </div>
              <h3 className="text-xl font-bold text-foreground mb-2">
                {feature.title}
              </h3>
              <p className="text-muted-foreground">{feature.description}</p>
            </Card>
          ))}
        </div>
      </section>

      {/* Footer */}
      <footer className="container mx-auto px-4 py-8 mt-12 text-center">
        <p className="text-sm text-muted-foreground">
          © 2025 Zaphira Organic Farms. All rights reserved.
        </p>
      </footer>
    </div>
  );
};

export default Index;
