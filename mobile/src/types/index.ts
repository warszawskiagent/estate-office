export interface AuthUser {
  user_id: number;
  display_name: string;
  email: string;
  role: 'administrator' | 'manager' | 'agent' | 'user';
  token: string;
  profile: AgentProfile | null;
}

export interface AgentProfile {
  phone: string;
  email: string;
  city: string;
  postal_code: string;
  bio: string;
  photo_url: string;
  office_id: number | null;
}

export interface DashboardStats {
  properties: number;
  agreements: number;
  searches: number;
  clients: number;
}

export interface DashboardData {
  stats: DashboardStats;
  recent_properties: Property[];
  recent_agreements: Agreement[];
}

export interface Property {
  id: number;
  offer_number: string;
  transaction_type: TransactionType;
  property_type: PropertyType;
  street: string;
  building_no: string;
  apartment_no: string;
  postal_code: string;
  city: string;
  district?: string;
  price: number | null;
  price_currency: string;
  area: number | null;
  price_per_m2: number | null;
  rooms: number | null;
  floor_no: number | null;
  owner_user_id: number;
  owner_name: string;
  is_sold: boolean;
  is_rented: boolean;
  is_new_offer: boolean;
  is_new_price: boolean;
  export_www: boolean;
  primary_photo: string;
  created_at: string;
  updated_at: string;
}

export interface PropertyDetail extends Property {
  description: string;
  land_registry_no: string;
  no_land_registry: boolean;
  latitude: number | null;
  longitude: number | null;
  admin_rent: number | null;
  bedrooms: number | null;
  bathrooms: number | null;
  toilets: number | null;
  year_built: number | null;
  floors_total: number | null;
  county: string;
  legal_status: string;
  building_finish: string;
  house_type: string;
  plot_shape: string;
  plot_area: number | null;
  exposure_json: string[] | null;
  view_json: string[] | null;
  layout_json: string[] | null;
  kitchen_type: string;
  parking_json: Record<string, unknown> | null;
  media_json: Record<string, unknown> | null;
  amenities_json: Record<string, unknown> | null;
  equipment_json: string[] | null;
  extra_areas_json: Record<string, unknown> | null;
  tags_json: string[] | null;
  is_exclusive: boolean;
  no_commission: boolean;
  is_mls_offer: boolean;
  is_premium: boolean;
  export_portals: boolean;
  agreement_id: number | null;
  media: PropertyMedia[];
}

export interface PropertyMedia {
  id: number;
  media_type: 'photo' | 'floor_plan_2d' | 'floor_plan_3d';
  media_url: string;
  attachment_id: number | null;
  position: number;
  is_primary: boolean;
}

export interface Client {
  id: number;
  client_type: 'person' | 'company';
  first_name: string;
  last_name: string;
  company_name: string;
  phone: string;
  email: string;
  owner_user_id: number;
  owner_name: string;
  address_city: string;
  address_street: string;
  created_at: string;
  updated_at: string;
}

export interface ClientDetail extends Client {
  representative_name: string;
  website: string;
  pesel: string;
  document_type: string;
  document_number: string;
  nip: string;
  krs: string;
  regon: string;
  addresses: ClientAddress[];
  agreements: AgreementSummary[];
}

export interface ClientAddress {
  id: number;
  address_type: 'main' | 'correspondence';
  street: string;
  building_no: string;
  apartment_no: string;
  postal_code: string;
  city: string;
  country: string;
}

export interface AgreementSummary {
  id: number;
  agreement_number: string;
  transaction_type: TransactionType;
  current_stage: string;
  date_signed: string;
  date_end: string | null;
  is_indefinite: boolean;
}

export interface Agreement {
  id: number;
  agreement_number: string;
  transaction_type: TransactionType;
  date_signed: string;
  date_end: string | null;
  is_indefinite: boolean;
  current_stage: string;
  commission_amount: number | null;
  commission_unit: string;
  owner_user_id: number;
  owner_name: string;
  created_at: string;
  updated_at: string;
}

export interface AgreementDetail extends Agreement {
  is_exclusive: boolean;
  commission_split_enabled: boolean;
  commission_stages_json: unknown;
  clients: AgreementClient[];
  stages: AgreementStage[];
  properties: PropertySummary[];
  searches: SearchSummary[];
}

export interface AgreementClient {
  id: number;
  client_type: 'person' | 'company';
  first_name: string;
  last_name: string;
  company_name: string;
  phone: string;
  email: string;
  relation_role: string;
}

export interface AgreementStage {
  id: number;
  stage_name: string;
  stage_date: string;
  created_at: string;
}

export interface PropertySummary {
  id: number;
  offer_number: string;
  property_type: PropertyType;
  street: string;
  building_no: string;
  city: string;
  price: number | null;
  price_currency: string;
  area: number | null;
}

export interface SearchSummary {
  id: number;
  search_number: string;
  transaction_type: TransactionType;
  property_type: string;
  budget_from: number | null;
  budget_to: number | null;
  location_text: string;
}

export interface Search {
  id: number;
  search_number: string;
  transaction_type: TransactionType;
  property_type: string;
  budget_from: number | null;
  budget_to: number | null;
  area_from: number | null;
  area_to: number | null;
  rooms_from: number | null;
  rooms_to: number | null;
  location_text: string;
  owner_user_id: number;
  owner_name: string;
  created_at: string;
  updated_at: string;
}

export interface SearchDetail extends Search {
  floor_from: number | null;
  floor_to: number | null;
  description: string;
  criteria_json: unknown;
  clients: SearchClient[];
}

export interface SearchClient {
  id: number;
  client_type: 'person' | 'company';
  first_name: string;
  last_name: string;
  company_name: string;
  phone: string;
  email: string;
}

export interface Agent {
  user_id: number;
  display_name: string;
  email: string;
  phone: string;
  city: string;
  bio: string;
  photo_url: string;
}

export interface PaginatedResponse<T> {
  items: T[];
  total: number;
  page: number;
  per_page: number;
  total_pages: number;
}

export type TransactionType = 'SPRZEDAZ' | 'KUPNO' | 'WYNAJEM' | 'NAJEM';
export type PropertyType = 'MIESZKANIE' | 'DOM' | 'DZIALKA' | 'LOKAL';

export const TRANSACTION_TYPE_LABELS: Record<TransactionType, string> = {
  SPRZEDAZ: 'Sprzedaż',
  KUPNO: 'Kupno',
  WYNAJEM: 'Wynajem',
  NAJEM: 'Najem',
};

export const PROPERTY_TYPE_LABELS: Record<PropertyType, string> = {
  MIESZKANIE: 'Mieszkanie',
  DOM: 'Dom',
  DZIALKA: 'Działka',
  LOKAL: 'Lokal H/U',
};

export const AGREEMENT_STAGES = [
  'Umowa Pośrednictwa',
  'Publikacja w MLS',
  'Przygotowanie oferty',
  'Publikacja oferty',
  'Marketing i prezentacje',
  'Oferta kupna',
  'Negocjacje',
  'Umowa przedwstępna',
  'Umowa przyrzeczona',
  'Przekazanie lokalu',
  'Umowa zakończona',
] as const;
