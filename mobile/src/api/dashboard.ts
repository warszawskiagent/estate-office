import { DashboardData } from '../types';
import { getApiClient } from './client';

export async function fetchDashboard(): Promise<DashboardData> {
  const res = await getApiClient().get<DashboardData>('/dashboard');
  return res.data;
}
