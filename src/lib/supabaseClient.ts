import { createClient } from '@supabase/supabase-js';

const supabaseUrl = 'https://xhfaesrsbtbplxvjzmnn.supabase.co';
const supabaseAnonKey = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InhoZmFlc3JzYnRicGx4dmp6bW5uIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NjAyMTc1MTcsImV4cCI6MjA3NTc5MzUxN30.hASJoCc5Zegpi-pZo0VHOHmw3p-g4EAjq0bwdMbNQaA';

export const supabase = createClient(supabaseUrl, supabaseAnonKey);
