import { api } from './client';
import type {
  ShopProduct, ShopOrder, OrderPaymentMethod,
  POSCart, POSSale, POSPaymentMethod,
} from '../types/shop';

export interface ShopCustomer {
  id: number;
  name: string;
  phone?: string | null;
  email?: string | null;
}

export async function listCustomers(): Promise<ShopCustomer[]> {
  const { data } = await api.get<{ data: ShopCustomer[] }>('/shop/customers');
  return data.data;
}

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
  payment_proof?: { uri: string; name: string; type: string } | null;
}): Promise<POSSale> {
  // If a proof photo is attached we have to send multipart/form-data.
  if (payload.payment_proof) {
    const form = new FormData();
    form.append('payment_method', payload.payment_method);
    if (payload.customer_id !== undefined)   form.append('customer_id', String(payload.customer_id));
    if (payload.customer_name)               form.append('customer_name', payload.customer_name);
    if (payload.customer_phone)              form.append('customer_phone', payload.customer_phone);
    if (payload.amount_received !== undefined) form.append('amount_received', String(payload.amount_received));
    form.append('payment_proof', payload.payment_proof as any);

    const { data } = await api.post<{ data: POSSale }>('/shop/pos/checkout', form, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });
    return data.data;
  }

  const { payment_proof: _ignored, ...json } = payload;
  const { data } = await api.post<{ data: POSSale }>('/shop/pos/checkout', json);
  return data.data;
}

export async function markSalePaid(saleId: number, paid_method: 'Cash' | 'Cash Deposit'): Promise<POSSale> {
  const { data } = await api.post<{ data: POSSale }>(`/shop/pos/sales/${saleId}/mark-paid`, { paid_method });
  return data.data;
}
