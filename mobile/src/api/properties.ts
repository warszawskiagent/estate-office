import { PaginatedResponse, Property, PropertyDetail } from '../types';
import { getApiClient } from './client';

export interface PropertiesFilter {
  search?: string;
  page?: number;
  per_page?: number;
}

export async function fetchProperties(filter: PropertiesFilter = {}): Promise<PaginatedResponse<Property>> {
  const res = await getApiClient().get<PaginatedResponse<Property>>('/properties', { params: filter });
  return res.data;
}

export async function fetchProperty(id: number): Promise<PropertyDetail> {
  const res = await getApiClient().get<PropertyDetail>(`/properties/${id}`);
  return res.data;
}

export async function createProperty(data: Record<string, unknown>): Promise<PropertyDetail> {
  const res = await getApiClient().post<PropertyDetail>('/properties', data);
  return res.data;
}

export async function updateProperty(id: number, data: Record<string, unknown>): Promise<PropertyDetail> {
  const res = await getApiClient().put<PropertyDetail>(`/properties/${id}`, data);
  return res.data;
}

export async function deleteProperty(id: number): Promise<void> {
  await getApiClient().delete(`/properties/${id}`);
}
