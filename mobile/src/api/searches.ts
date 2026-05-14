import { PaginatedResponse, Search, SearchDetail } from '../types';
import { getApiClient } from './client';

export interface SearchesFilter {
  search?: string;
  page?: number;
  per_page?: number;
}

export async function fetchSearches(filter: SearchesFilter = {}): Promise<PaginatedResponse<Search>> {
  const res = await getApiClient().get<PaginatedResponse<Search>>('/searches', { params: filter });
  return res.data;
}

export async function fetchSearch(id: number): Promise<SearchDetail> {
  const res = await getApiClient().get<SearchDetail>(`/searches/${id}`);
  return res.data;
}

export async function createSearch(data: Record<string, unknown>): Promise<SearchDetail> {
  const res = await getApiClient().post<SearchDetail>('/searches', data);
  return res.data;
}

export async function updateSearch(id: number, data: Record<string, unknown>): Promise<SearchDetail> {
  const res = await getApiClient().put<SearchDetail>(`/searches/${id}`, data);
  return res.data;
}

export async function deleteSearch(id: number): Promise<void> {
  await getApiClient().delete(`/searches/${id}`);
}
