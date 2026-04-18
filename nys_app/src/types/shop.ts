export interface ShopProduct {
  id: number;
  product_name: string;
  product_type?: string | null;
  description?: string | null;
  int_stock: number;
  stock_level: number;
  actual_price: number;
  img_path?: string | null;
}

export interface ShopOrderItem {
  id: number;
  product_id: number;
  qty: number;
  unit_price: number;
  total_price: number;
  product?: ShopProduct;
}

export type OrderStatus = 'pending_approval' | 'approved' | 'rejected' | 'completed';
export type OrderPaymentMethod = 'pay_in_store' | 'deposit' | 'credit';

export interface ShopOrder {
  id: number;
  status: OrderStatus;
  total_amount: number;
  notes?: string | null;
  payment_method: OrderPaymentMethod;
  payment_proof_path?: string | null;
  rejection_reason?: string | null;
  created_at: string;
  paid_at?: string | null;
  items: ShopOrderItem[];
}

export type POSPaymentMethod = 'Cash' | 'Cash Deposit' | 'Credit';

export interface POSCartItem {
  id: number;
  product_id: number;
  qty: number;
  unit_price: number;
  total_price: number;
  product?: ShopProduct;
}

export interface POSCart {
  id: number;
  items: POSCartItem[];
  total_amount: number;
}

export interface POSSale {
  id: number;
  customer_name?: string | null;
  payment_method: POSPaymentMethod;
  amount_received?: number | null;
  change_amount?: number | null;
  total_amount: number;
  is_paid: boolean;
  paid_method?: string | null;
  items: {
    id: number; product_id: number; product_name: string;
    qty_sold: number; actual_price: number; total_price: number;
  }[];
}
