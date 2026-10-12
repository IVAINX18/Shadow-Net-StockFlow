import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable, map } from 'rxjs';
import { API_BASE_URL } from './api-base-url.token';
import type { ItemResponse, ListParams, PaginatedResponse } from '../models/api.model';

/**
 * Cliente base de la API. Centraliza la URL base, el prefijo /api/v1 y el
 * armado de parametros de consulta; es el unico lugar que conoce la forma del
 * contrato. Los servicios de cada funcionalidad lo usan y los componentes
 * nunca tocan HttpClient (frontend-architecture.md §6).
 */
@Injectable({ providedIn: 'root' })
export class ApiService {
  private readonly http = inject(HttpClient);
  private readonly baseUrl = inject(API_BASE_URL);

  /** Solicita la cookie CSRF de Sanctum antes de la primera peticion mutadora. */
  public bootstrapCsrf(): Observable<void> {
    return this.http.get<void>(`${this.baseUrl}/sanctum/csrf-cookie`);
  }

  /** GET tipado de un recurso unico bajo /api/v1. */
  public get<T>(path: string, params?: ListParams): Observable<T> {
    return this.http.get<T>(this.url(path), { params: this.toParams(params) });
  }

  /** GET tipado de una coleccion paginada (data + links + meta). */
  public getPage<T>(path: string, params?: ListParams): Observable<PaginatedResponse<T>> {
    return this.http.get<PaginatedResponse<T>>(this.url(path), { params: this.toParams(params) });
  }

  /** POST tipado. */
  public post<T>(path: string, body: unknown): Observable<T> {
    return this.http.post<T>(this.url(path), body);
  }

  /** PUT tipado. */
  public put<T>(path: string, body: unknown): Observable<T> {
    return this.http.put<T>(this.url(path), body);
  }

  /** DELETE tipado. */
  public delete<T>(path: string): Observable<T> {
    return this.http.delete<T>(this.url(path));
  }

  // El sobre de exito de la API envuelve recursos unicos en `data`; expongo este
  // desempaquetado para que los servicios no repitan el acceso a `.data`.
  /** Extrae `data` de una respuesta de recurso unico. */
  public unwrap<T>(path: string, params?: ListParams): Observable<T> {
    return this.http
      .get<ItemResponse<T>>(this.url(path), { params: this.toParams(params) })
      .pipe(map((response) => response.data));
  }

  private url(path: string): string {
    return `${this.baseUrl}/api/v1${path.startsWith('/') ? path : `/${path}`}`;
  }

  private toParams(params?: ListParams): HttpParams {
    let httpParams = new HttpParams();

    if (params === undefined) {
      return httpParams;
    }

    for (const [key, value] of Object.entries(params)) {
      if (value !== undefined && value !== null) {
        httpParams = httpParams.set(key, String(value));
      }
    }

    return httpParams;
  }
}
