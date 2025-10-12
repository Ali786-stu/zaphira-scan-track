import { useEffect, useState } from 'react';
import { supabase } from '@/lib/supabaseClient';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { toast } from '@/hooks/use-toast';
import { Calendar } from 'lucide-react';
import { format } from 'date-fns';

interface Holiday {
  id: string;
  date: string;
  name: string;
  description: string;
}

export default function HolidayList() {
  const [holidays, setHolidays] = useState<Holiday[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    fetchHolidays();
  }, []);

  const fetchHolidays = async () => {
    try {
      const { data, error } = await supabase
        .from('holidays')
        .select('*')
        .order('date', { ascending: true });

      if (error) throw error;

      setHolidays(data || []);
      toast({ title: 'Holidays loaded successfully' });
    } catch (error: any) {
      toast({ 
        title: 'Error loading holidays', 
        description: error.message,
        variant: 'destructive' 
      });
    } finally {
      setLoading(false);
    }
  };

  if (loading) {
    return (
      <div className="flex items-center justify-center h-96">
        <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-primary"></div>
      </div>
    );
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle className="flex items-center gap-2">
          <Calendar className="h-5 w-5" />
          Holiday List
        </CardTitle>
      </CardHeader>
      <CardContent>
        <div className="space-y-4">
          {holidays.length === 0 ? (
            <p className="text-center text-muted-foreground py-8">No holidays found</p>
          ) : (
            holidays.map((holiday) => (
              <div 
                key={holiday.id} 
                className="flex items-start gap-4 p-4 rounded-lg bg-muted/50 hover:bg-muted transition-colors"
              >
                <div className="flex flex-col items-center justify-center bg-primary text-primary-foreground rounded-lg p-3 min-w-[60px]">
                  <span className="text-2xl font-bold">
                    {format(new Date(holiday.date), 'd')}
                  </span>
                  <span className="text-xs">
                    {format(new Date(holiday.date), 'MMM')}
                  </span>
                </div>
                <div className="flex-1">
                  <h3 className="font-semibold text-lg">{holiday.name}</h3>
                  <p className="text-sm text-muted-foreground mt-1">{holiday.description}</p>
                  <p className="text-xs text-muted-foreground mt-2">
                    {format(new Date(holiday.date), 'EEEE, MMMM d, yyyy')}
                  </p>
                </div>
              </div>
            ))
          )}
        </div>
      </CardContent>
    </Card>
  );
}
