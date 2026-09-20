import { apiRequest } from "./client";
import type { SearchResultGroup } from "./types";

export const searchApi = {
  search(q: string) {
    return apiRequest<SearchResultGroup[]>(`/search?q=${encodeURIComponent(q)}`);
  },
};
