import { Agent } from '../types';
import { getApiClient } from './client';

export async function fetchAgents(): Promise<Agent[]> {
  const res = await getApiClient().get<{ items: Agent[] }>('/agents');
  return res.data.items;
}
