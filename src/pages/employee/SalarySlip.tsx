import { useEffect, useState } from 'react';
import { supabase } from '@/lib/supabaseClient';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { toast } from '@/hooks/use-toast';
import { Download, FileText } from 'lucide-react';

interface SalarySlip {
  id: string;
  month: string;
  year: number;
  amount: number;
  download_url: string;
}

export default function SalarySlip() {
  const [salarySlips, setSalarySlips] = useState<SalarySlip[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    fetchSalarySlips();
  }, []);

  const fetchSalarySlips = async () => {
    try {
      const { data: { user } } = await supabase.auth.getUser();
      if (!user) return;

      const { data, error } = await supabase
        .from('salary_slip')
        .select('*')
        .eq('employee_id', user.id)
        .order('year', { ascending: false })
        .order('month', { ascending: false });

      if (error) throw error;

      setSalarySlips(data || []);
      toast({ title: 'Salary slips loaded successfully' });
    } catch (error: any) {
      toast({ 
        title: 'Error loading salary slips', 
        description: error.message,
        variant: 'destructive' 
      });
    } finally {
      setLoading(false);
    }
  };

  const handleDownload = (url: string, month: string, year: number) => {
    if (url) {
      window.open(url, '_blank');
      toast({ title: `Downloading salary slip for ${month} ${year}` });
    } else {
      toast({ 
        title: 'Download not available', 
        description: 'No file URL provided',
        variant: 'destructive' 
      });
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
          <FileText className="h-5 w-5" />
          Salary Slips
        </CardTitle>
      </CardHeader>
      <CardContent>
        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
          {salarySlips.length === 0 ? (
            <p className="text-center text-muted-foreground py-8 col-span-full">
              No salary slips available
            </p>
          ) : (
            salarySlips.map((slip) => (
              <Card key={slip.id} className="overflow-hidden">
                <CardContent className="p-6">
                  <div className="flex items-start justify-between mb-4">
                    <div>
                      <h3 className="font-semibold text-lg">{slip.month} {slip.year}</h3>
                      <p className="text-2xl font-bold text-primary mt-2">
                        ₹{slip.amount.toLocaleString()}
                      </p>
                    </div>
                    <FileText className="h-8 w-8 text-muted-foreground" />
                  </div>
                  <Button 
                    onClick={() => handleDownload(slip.download_url, slip.month, slip.year)}
                    className="w-full"
                    variant="outline"
                  >
                    <Download className="h-4 w-4 mr-2" />
                    Download
                  </Button>
                </CardContent>
              </Card>
            ))
          )}
        </div>
      </CardContent>
    </Card>
  );
}
