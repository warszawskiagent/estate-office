import { Client, ClientDetail, PaginatedResponse } from '../types';
import { getApiClient } from './client';

export interface ClientsFilter {
  search?: string;
  page?: number;
  per_page?: number;
}

export async function fetchClients(filter: ClientsFilter = {}): Promise<PaginatedResponse<Client>> {
  const res = await getApiClient().get<PaginatedResponse<Client>>('/clients', { params: filter });
  return res.data;
}

export async function fetchClient(id: number): Promise<ClientDetail> {
  const res = await getApiClient().get<ClientDetail>(`/clients/${id}`);
  return res.data;
}

export async function createClient(data: Record<string, unknown>): Promise<ClientDetail> {
  const res = await getApiClient().post<ClientDetail>('/clients', data);
  return res.data;
}

export async function updateClient(id: number, data: Record<string, unknown>): Promise<ClientDetail> {
  const res = await getApiClient().put<ClientDetail>(`/clients/${id}`, data);
  return res.data;
}

export async function deleteClient(id: number): Promise<void> {
  await getApiClient().delete(`/clients/${id}`);
}
