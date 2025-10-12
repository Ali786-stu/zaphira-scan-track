import { useEffect, useState } from 'react';
import { Outlet, NavLink, useNavigate } from 'react-router-dom';
import { supabase } from '@/lib/supabaseClient';
import { Button } from '@/components/ui/button';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Sheet, SheetContent, SheetTrigger } from '@/components/ui/sheet';
import { 
  Menu, 
  Calendar, 
  User, 
  FileText, 
  ClipboardList, 
  PartyPopper, 
  DollarSign,
  LogOut
} from 'lucide-react';
import { cn } from '@/lib/utils';

const navigationItems = [
  { name: 'Attendance List', path: '/employee/attendance', icon: ClipboardList },
  { name: 'My Profile', path: '/employee/profile', icon: User },
  { name: 'Apply Leave', path: '/employee/apply-leave', icon: FileText },
  { name: 'Leave Details', path: '/employee/leave-details', icon: Calendar },
  { name: 'Holiday List', path: '/employee/holidays', icon: PartyPopper },
  { name: 'Salary Slips', path: '/employee/salary', icon: DollarSign },
];

export default function EmployeeLayout() {
  const [userName, setUserName] = useState('Employee');
  const [open, setOpen] = useState(false);
  const navigate = useNavigate();

  useEffect(() => {
    fetchUserProfile();
  }, []);

  const fetchUserProfile = async () => {
    try {
      const { data: { user } } = await supabase.auth.getUser();
      if (!user) return;

      const { data } = await supabase
        .from('profiles')
        .select('name')
        .eq('id', user.id)
        .single();

      if (data) setUserName(data.name);
    } catch (error) {
      console.error('Error fetching profile:', error);
    }
  };

  const handleLogout = async () => {
    await supabase.auth.signOut();
    navigate('/login');
  };

  const NavContent = () => (
    <nav className="space-y-2">
      {navigationItems.map((item) => (
        <NavLink
          key={item.path}
          to={item.path}
          onClick={() => setOpen(false)}
          className={({ isActive }) =>
            cn(
              'flex items-center gap-3 px-4 py-3 rounded-lg transition-all',
              isActive
                ? 'bg-primary text-primary-foreground font-medium'
                : 'text-muted-foreground hover:bg-muted hover:text-foreground'
            )
          }
        >
          <item.icon className="h-5 w-5" />
          <span>{item.name}</span>
        </NavLink>
      ))}
      <Button
        variant="ghost"
        className="w-full justify-start gap-3 px-4 py-3 text-muted-foreground hover:text-destructive"
        onClick={handleLogout}
      >
        <LogOut className="h-5 w-5" />
        <span>Logout</span>
      </Button>
    </nav>
  );

  return (
    <div className="min-h-screen bg-background">
      {/* Header */}
      <header className="sticky top-0 z-50 border-b bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/60">
        <div className="flex h-16 items-center gap-4 px-4 md:px-6">
          <Sheet open={open} onOpenChange={setOpen}>
            <SheetTrigger asChild>
              <Button variant="ghost" size="icon" className="md:hidden">
                <Menu className="h-5 w-5" />
              </Button>
            </SheetTrigger>
            <SheetContent side="left" className="w-64 p-0">
              <div className="p-6 border-b">
                <h2 className="text-lg font-semibold text-primary">Zaphira Farms</h2>
              </div>
              <div className="p-4">
                <NavContent />
              </div>
            </SheetContent>
          </Sheet>

          <div className="flex-1">
            <h1 className="text-xl font-bold text-primary">Zaphira Organic Farms</h1>
          </div>

          <div className="flex items-center gap-3">
            <div className="hidden md:block text-right">
              <p className="text-sm font-medium">{userName}</p>
              <p className="text-xs text-muted-foreground">Employee</p>
            </div>
            <Avatar>
              <AvatarFallback className="bg-primary text-primary-foreground">
                {userName.split(' ').map(n => n[0]).join('')}
              </AvatarFallback>
            </Avatar>
          </div>
        </div>
      </header>

      {/* Main Layout */}
      <div className="flex">
        {/* Desktop Sidebar */}
        <aside className="hidden md:flex w-64 flex-col border-r min-h-[calc(100vh-4rem)]">
          <div className="p-4">
            <NavContent />
          </div>
        </aside>

        {/* Main Content */}
        <main className="flex-1 p-4 md:p-6">
          <Outlet />
        </main>
      </div>
    </div>
  );
}
