export interface AnimalRef { id: number; animal_tag: string; animal_name?: string }
export interface FarmRef   { id: number; name: string }

export interface AnimalEvent {
  id: number;
  account_id: number;
  farm_id: number;
  animal_id: number;
  event_type: string;
  event_date: string;
  cost: number;
  cost_type: string;
  meta?: Record<string, any>;
  animal?: AnimalRef;
  farm?: FarmRef;
  created_at: string;
}

export interface DashboardData {
  total_farms: number;
  total_animals: number;
  animals_by_status: Record<string, number>;
  animals_by_type: Record<string, number>;
  animals_per_farm: Record<string, number>;
  low_stock_count: number;
  pnl: { income: number; investment: number; expense: number; loss: number; profit: number; period: string };
  recent_events: AnimalEvent[];
}

export interface Animal {
  id: number;
  farm_id: number;
  animal_type_id: number;
  breed_id?: number;
  animal_tag: string;
  farm_tag?: string;
  sex?: string;
  date_of_birth?: string;
  animal_name?: string;
  status?: string;
  notes?: string;
  animalType?: { id: number; name: string };
  breed?: { id: number; breed_name: string };
  farm?: FarmRef;
}

export interface Paginated<T> {
  data: T[];
  current_page: number;
  last_page: number;
  total: number;
  per_page: number;
}

export type CostType = 'income' | 'expense' | 'loss' | 'running' | 'birth' | 'investment';
export type MovementType = 'purchase' | 'issue' | 'adjustment';
