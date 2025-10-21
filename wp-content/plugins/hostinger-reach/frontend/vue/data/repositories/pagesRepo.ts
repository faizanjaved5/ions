import { useGeneralDataStore } from '@/stores/generalDataStore';
import { Header } from '@/types/enums';
import type { AuthorizeRequestHeaders } from '@/types/models/httpModels';
import type { WordPressPagesList } from '@/types/models/pagesModels';
import { generateCorrelationId } from '@/utils/helpers';
import httpService from '@/utils/services/httpService';

const URL = `${hostinger_reach_reach_data.rest_base_url}wp/v2`;

export interface PaginationParams {
	page?: number;
	perPage?: number;
}

export interface PaginatedResponse<T> {
	data: T;
	pagination: {
		currentPage: number;
		totalPages: number;
		totalItems: number;
		perPage: number;
	};
}

export const pagesRepo = {
	getPagesWithSubscriptionBlock: (paginationParams?: PaginationParams, headers?: AuthorizeRequestHeaders) => {
		const { nonce, totalFormPages } = useGeneralDataStore();
		const page = paginationParams?.page || 1;
		const perPage = paginationParams?.perPage || 5;
		const totalItems = totalFormPages || 0;
		const totalPages = Math.ceil(totalItems / perPage);

		const params = new URLSearchParams();
		params.append('hostinger_reach_page_query', '1');
		params.append('page', page.toString());
		params.append('per_page', perPage.toString());

		const config = {
			headers: {
				[Header.CORRELATION_ID]: headers?.[Header.CORRELATION_ID] || generateCorrelationId(),
				[Header.WP_NONCE]: nonce
			}
		};

		return httpService.get<WordPressPagesList>(`${URL}/pages?${params.toString()}`, config).then(([data, error]) => {
			if (error) {
				return [null, error];
			}

			const paginatedResponse: PaginatedResponse<WordPressPagesList> = {
				data: data || [],
				pagination: {
					currentPage: page,
					totalPages,
					totalItems,
					perPage
				}
			};

			return [paginatedResponse, null];
		});
	}
};
