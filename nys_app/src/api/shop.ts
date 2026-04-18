import { api } from './client';
import type {
  ShopProduct, ShopOrder, OrderPaymentMethod,
  POSCart, POSSale, POSPaymentMethod,
} from '../types/shop';

// ── Products ──────────────────────────────────────────────────────────────
export async function listProducts(): Promise<ShopProduct[]> {
  const { data } = await api.get<{ data: ShopProduct[] }>('/shop/products');
  return data.data;
}

// ── Customer orders ───────────────────────────────────────────────────────
export async function placeOrder(payload: {
  items: { product_id: number; qty: number }[];
  payment_method: OrderPaymentMethod;
  notes?: string;
}): Promise<ShopOrder> {
  const { data } = await api.post<{ data: ShopOrder }>('/shop/orders', payload);
  return data.data;
}

export async function getMyOrders(): Promise<ShopOrder[]> {
  const { data } = await api.get<{ data: ShopOrder[] }>('/shop/orders/my');
  return data.data;
}

// ── Admin orders ──────────────────────────────────────────────────────────
export async function listOrders(params: { status?: string; page?: number } = {}) {
  const { data } = await api.get<{ data: { data: ShopOrder[]; current_page: number; last_page: number } }>('/shop/orders', { params });
  return data.data;
}

export async function updateOrder(id: number, payload: {
  status?: 'approved' | 'rejected' | 'completed';
  rejection_reason?: string;
  mark_paid?: boolean;
}): Promise<ShopOrder> {
  const { data } = await api.put<{ data: ShopOrder }>(`/shop/orders/${id}`, payload);
  return data.data;
}

// ── POS ───────────────────────────────────────────────────────────────────
export async function getCart(): Promise<POSCart> {
  const { data } = await api.get<{ data: POSCart }>('/shop/pos/cart');
  return data.data;
}

export async function addCartItem(product_id: number, qty: number): Promise<POSCart> {
  const { data } = await api.post<{ data: POSCart }>('/shop/pos/cart/items', { product_id, qty });
  return data.data;
}

export async function removeCartItem(itemId: number): Promise<POSCart> {
  const { data } = await api.delete<{ data: POSCart }>(`/shop/pos/cart/items/${itemId}`);
  return data.data;
}

export async function posCheckout(payload: {
  payment_method: POSPaymentMethod;
  customer_id?: number;
  customer_name?: string;
  customer_phone?: string;
  amount_received?: number;
}): Promise<POSSale> {
  const { data } = await api.post<{ data: POSSale }>('/shop/pos/checkout', payload);
  return data.data;
}

export async function markSalePaid(saleId: number, paid_method: 'Cash' | 'Cash Deposit'): Promise<POSSale> {
  const { data } = await api.post<{ data: POSSale }>(`/shop/pos/sales/${saleId}/mark-paid`, { paid_method });
  return data.data;
}
