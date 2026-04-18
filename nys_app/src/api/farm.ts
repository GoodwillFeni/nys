import { api } from './client';
import type {
  Animal, AnimalEvent, CostType, DashboardData, MovementType, Paginated,
} from '../types/farm';

export async function getDashboard(): Promise<DashboardData> {
  const { data } = await api.get<DashboardData>('/farm/dashboard');
  return data;
}

export async function listAnimals(params: {
  page?: number; farm_id?: number; status?: string; search?: string;
} = {}): Promise<Paginated<Animal>> {
  const { data } = await api.get<Paginated<Animal>>('/farm/animals', { params });
  return data;
}

export async function updateAnimal(id: number, payload: Partial<{
  animal_type_id: number;
  animal_tag: number | string;
  sex: string;
  date_of_birth: string;
  name: string;
  notes: string;
  breed_id: number;
}>): Promise<Animal> {
  const { data } = await api.put<Animal>(`/farm/animals/${id}`, payload);
  return data;
}

export async function logEvent(payload: {
  account_id: number;
  farm_id: number;
  animal_id: number;
  event_type: string;
  event_date: string;
  cost?: number;
  cost_type?: CostType;
  meta?: Record<string, any>;
}): Promise<AnimalEvent> {
  const { data } = await api.post<AnimalEvent>('/animal-events/single', payload);
  return data;
}

export async function recordInventoryMovement(payload: {
  farm_id: number;
  inventory_item_id: number;
  movement_type: MovementType;
  qty: number;
  unit_cost?: number;
  notes?: string;
  animal_id?: number;
}) {
  const { data } = await api.post('/farm/inventory/movements', payload);
  return data;
}
