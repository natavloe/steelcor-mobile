/** Типы ответов клиентского API (см. backend/app/Http/Resources/Client). */

export type Unit = 't' | 'kg' | 'm' | 'pc' | 'm2';

export interface Region {
  code: string;
  name: string;
  phone: string | null;
  email: string | null;
  timezone: string;
}

export interface Category {
  id: number;
  parent_id: number | null;
  name: string;
  slug: string;
  image: string | null;
  children?: Category[];
}

export interface CategoryFilter {
  code: string;
  name: string;
  unit: string | null;
  type: 'number' | 'text' | 'enum';
  min?: number | null;
  max?: number | null;
  values?: string[];
}

export interface Product {
  id: number;
  category_id: number;
  name: string;
  slug: string;
  grade: string | null;
  gost: string | null;
  attrs: Record<string, string | number>;
  base_unit: Unit;
  weight_per_m: string | null;
  std_length_mm: number | null;
  is_oversize: boolean;
  units: Unit[];
  availability: string | null;
  availability_label: string | null;
  price: {
    amount: string;
    unit: Unit;
    vat_included: boolean;
    valid_until: string | null;
    is_preliminary: true;
  } | null;
  lead_time_days: { min: number | null; max: number } | null;
}

export interface Paginated<T> {
  data: T[];
  meta?: { current_page: number; last_page: number; total: number };
}

export interface OrganizationBrief {
  id: number;
  name: string;
  inn: string;
  role: 'owner' | 'buyer' | 'accountant' | 'viewer';
  verification_status: 'unverified' | 'verified' | 'rejected';
}

export interface User {
  id: number;
  name: string | null;
  phone: string;
  email: string | null;
  default_region: string | null;
  organizations: OrganizationBrief[];
}

export interface CompanyLookupResult {
  type: 'legal' | 'ip';
  name_full: string;
  name_short: string;
  inn: string;
  kpp: string | null;
  ogrn: string | null;
  legal_address: string | null;
}

export interface Address {
  id: number;
  label: string | null;
  address: string;
  city: string | null;
  contact_name: string | null;
  contact_phone: string | null;
  access_notes: string | null;
}

export interface PickupPoint {
  id: number;
  name: string;
  address: string;
  work_hours: string | null;
  instructions: string | null;
}

export interface CartItem {
  id: number;
  product: Product;
  qty: string;
  unit: Unit;
  comment: string | null;
  preliminary_amount: string | null;
}

export interface Cart {
  items: CartItem[];
  preliminary_total: string;
  total_is_complete: boolean;
  theoretical_weight_kg: string;
  note: string;
}

export interface OfferLine {
  type: 'goods' | 'delivery' | 'service';
  name: string;
  grade: string | null;
  gost: string | null;
  size: string | null;
  qty: string;
  unit: Unit;
  unit_price: string;
  vat_rate: string;
  amount: string;
  lead_time_text: string | null;
}

export interface Offer {
  id: number;
  version: number;
  status: string;
  status_label: string;
  reason: string | null;
  valid_until: string | null;
  payment_terms: string;
  payment_terms_label: string;
  prepay_percent: string | null;
  deferral_days: number | null;
  lead_time_text: string | null;
  totals: { goods: string; goods_vat: string; delivery: string; services: string; total: string };
  lines: OfferLine[];
  sent_at: string | null;
  accepted_at: string | null;
}

export interface OrderDate {
  from: string;
  to: string | null;
  label: string;
}

export interface Order {
  id: number;
  number: string;
  created_at: string;
  organization: { id: number; name: string };
  region: string;
  status: string;
  status_label: string;
  payment_status: string;
  payment_status_label: string;
  delivery_status: string;
  delivery_status_label: string;
  documents_status: string;
  is_reagreement: boolean;
  total: string | null;
  action_required: { code: 'accept_terms' | 'pay'; label: string } | null;
  // Детально (GET /orders/{id})
  receive_method?: 'delivery' | 'pickup';
  delivery_address?: Address | null;
  pickup_point?: PickupPoint | null;
  desired?: { from: string | null; to: string | null; time_slot: string | null };
  contact?: { name: string | null; phone: string | null };
  comment?: string | null;
  items?: { product_id: number | null; name: string; qty: string; unit: Unit; comment: string | null }[];
  dates?: Record<string, OrderDate | null>;
  current_offer?: Offer | null;
  invoices?: { id: number; number: string; amount: string; remaining: string; due_date: string | null; kind: string }[];
  shipments?: {
    carrier: string;
    carrier_label: string;
    status: string;
    status_label: string;
    tracking_number: string | null;
    tracking_url: string | null;
    planned_date: string | null;
    cost: { amount: string; is_confirmed: boolean } | null;
    events: { status_label: string; text: string | null; at: string }[];
  }[];
  can_cancel?: boolean;
}
