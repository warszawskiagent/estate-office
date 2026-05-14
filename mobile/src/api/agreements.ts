import { Agreement, AgreementDetail, PaginatedResponse } from '../types';
import { getApiClient } from './client';

export interface AgreementsFilter {
  search?: string;
  page?: number;
  per_page?: number;
}

export async function fetchAgreements(filter: AgreementsFilter = {}): Promise<PaginatedResponse<Agreement>> {
  const res = await getApiClient().get<PaginatedResponse<Agreement>>('/agreements', { params: filter });
  return res.data;
}

export async function fetchAgreement(id: number): Promise<AgreementDetail> {
  const res = await getApiClient().get<AgreementDetail>(`/agreements/${id}`);
  return res.data;
}

export async function createAgreement(data: Record<string, unknown>): Promise<AgreementDetail> {
  const res = await getApiClient().post<AgreementDetail>('/agreements', data);
  return res.data;
}

export async function updateAgreement(id: number, data: Record<string, unknown>): Promise<AgreementDetail> {
  const res = await getApiClient().put<AgreementDetail>(`/agreements/${id}`, data);
  return res.data;
}

export async function deleteAgreement(id: number): Promise<void> {
  await getApiClient().delete(`/agreements/${id}`);
}

export async function addAgreementStage(
  id: number,
  stage_name: string,
  stage_date: string
): Promise<{ success: boolean; current_stage: string; stages: unknown[] }> {
  const res = await getApiClient().post(`/agreements/${id}/stages`, { stage_name, stage_date });
  return res.data;
}
