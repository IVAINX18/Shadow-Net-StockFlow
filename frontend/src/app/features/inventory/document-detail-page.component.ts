import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { MatButtonModule } from '@angular/material/button';
import { MatDialog, MatDialogModule } from '@angular/material/dialog';
import { MatIconModule } from '@angular/material/icon';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { AuthService } from '../../core/services/auth.service';
import { AppError } from '../../core/models/app-error.model';
import type {
  InventoryDocument,
  InventoryDocumentStatus,
} from '../../core/models/inventory.model';
import type { ChipTone } from '../../shared/components/status-chip/status-chip.component';
import { PageHeaderComponent } from '../../shared/components/page-header/page-header.component';
import { ErrorStateComponent } from '../../shared/components/error-state/error-state.component';
import { LoadingSkeletonComponent } from '../../shared/components/loading-skeleton/loading-skeleton.component';
import { StatusChipComponent } from '../../shared/components/status-chip/status-chip.component';
import {
  ConfirmDialogComponent,
  type ConfirmDialogData,
} from '../../shared/components/confirm-dialog/confirm-dialog.component';
import { InventoryDocumentsService } from './inventory-documents.service';
import { ReverseDialogComponent } from './reverse-dialog.component';

const STATUS_TONES: Readonly<Record<InventoryDocumentStatus, ChipTone>> = {
  borrador: 'neutral',
  confirmado: 'success',
  revertido: 'warning',
  descartado: 'error',
};

const STATUS_LABELS: Readonly<Record<InventoryDocumentStatus, string>> = {
  borrador: 'Borrador',
  confirmado: 'Confirmado',
  revertido: 'Revertido',
  descartado: 'Descartado',
};

/**
 * Detalle de un documento con sus lineas y, cuando existen, sus movimientos y
 * su reversion. Las acciones de ciclo (confirmar, descartar, revertir) viven
 * aqui porque necesitan el contexto completo; el 409 de negocio se muestra
 * como aviso junto a la cabecera, nunca como traza.
 */
@Component({
  selector: 'app-document-detail-page',
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [
    RouterLink,
    MatButtonModule,
    MatDialogModule,
    MatIconModule,
    PageHeaderComponent,
    ErrorStateComponent,
    LoadingSkeletonComponent,
    StatusChipComponent,
  ],
  templateUrl: './document-detail-page.component.html',
  styleUrl: './document-detail-page.component.scss',
})
export class DocumentDetailPageComponent {
  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);
  private readonly documents = inject(InventoryDocumentsService);
  private readonly auth = inject(AuthService);
  private readonly dialog = inject(MatDialog);

  protected readonly canConfirm = this.auth.hasPermission('inventory.confirm');
  protected readonly canCancel = this.auth.hasPermission('inventory.create');
  protected readonly canReverse = this.auth.hasPermission('inventory.reverse');

  protected readonly document = signal<InventoryDocument | null>(null);
  protected readonly loading = signal(true);
  protected readonly error = signal<AppError | null>(null);
  protected readonly errorMessage = computed(() => this.error()?.message ?? null);
  protected readonly errorId = computed(() => this.error()?.errorId ?? null);
  protected readonly notice = signal<string | null>(null);
  protected readonly acting = signal(false);

  protected readonly statusTone = computed<ChipTone>(
    () => STATUS_TONES[this.document()?.status ?? 'borrador'],
  );
  protected readonly statusLabel = computed<string>(
    () => STATUS_LABELS[this.document()?.status ?? 'borrador'],
  );

  public constructor() {
    this.load();
  }

  protected load(): void {
    const uuid = this.route.snapshot.paramMap.get('uuid') ?? '';

    if (uuid === '') {
      this.loading.set(false);
      this.error.set(
        new AppError({
          kind: 'business',
          code: 'NO_ENCONTRADO',
          message: 'El documento solicitado no existe.',
          status: 404,
        }),
      );
      return;
    }

    this.loading.set(true);
    this.error.set(null);

    this.documents.get(uuid).subscribe({
      next: (document) => {
        this.document.set(document);
        this.loading.set(false);
      },
      error: (error: unknown) => {
        this.document.set(null);
        this.error.set(error instanceof AppError ? error : null);
        this.loading.set(false);
      },
    });
  }

  protected confirmDocument(): void {
    const current = this.document();

    if (current === null) {
      return;
    }

    const data: ConfirmDialogData = {
      title: 'Confirmar documento',
      message:
        `El documento ${current.doc_number} generara sus movimientos de forma atomica. ` +
        'Si el stock no alcanza, la confirmacion se rechaza sin escribir nada.',
      confirmLabel: 'Confirmar',
      confirmColor: 'primary',
    };

    this.dialog
      .open<ConfirmDialogComponent, ConfirmDialogData, boolean>(ConfirmDialogComponent, {
        width: '440px',
        maxWidth: 'calc(100vw - 32px)',
        data,
      })
      .afterClosed()
      .subscribe((confirmed) => {
        if (confirmed === true) {
          this.runAction(() => this.documents.confirm(current.uuid));
        }
      });
  }

  protected cancelDocument(): void {
    const current = this.document();

    if (current === null) {
      return;
    }

    const data: ConfirmDialogData = {
      title: 'Descartar borrador',
      message:
        `El documento ${current.doc_number} quedara descartado. ` +
        'No mueve existencias porque nunca se confirmo.',
      confirmLabel: 'Descartar',
    };

    this.dialog
      .open<ConfirmDialogComponent, ConfirmDialogData, boolean>(ConfirmDialogComponent, {
        width: '440px',
        maxWidth: 'calc(100vw - 32px)',
        data,
      })
      .afterClosed()
      .subscribe((confirmed) => {
        if (confirmed === true) {
          this.runAction(() => this.documents.cancel(current.uuid));
        }
      });
  }

  protected reverseDocument(): void {
    const current = this.document();

    if (current === null) {
      return;
    }

    // El motivo es obligatorio en el backend: lo pido en dialogo propio con
    // validacion en lugar de reutilizar la confirmacion sin campos.
    this.dialog
      .open<ReverseDialogComponent, string, string | null>(ReverseDialogComponent, {
        width: '480px',
        maxWidth: 'calc(100vw - 32px)',
        data: current.doc_number,
      })
      .afterClosed()
      .subscribe((reason) => {
        if (reason !== null && reason !== undefined) {
          this.runAction(() => this.documents.reverse(current.uuid, { reason }));
        }
      });
  }

  protected backToList(document: InventoryDocument): string {
    return document.type === 'transferencia' ? '/transferencias' : '/inventario';
  }

  protected goBack(document: InventoryDocument): void {
    void this.router.navigate([this.backToList(document)]);
  }

  // Tras cada accion recargo el detalle desde el servidor en lugar de
  // parchar el estado local: el estado y los movimientos los decide el
  // backend dentro de su transaccion, no esta pantalla.
  private runAction(call: () => ReturnType<InventoryDocumentsService['confirm']>): void {
    this.acting.set(true);
    this.notice.set(null);

    call().subscribe({
      next: (updated) => {
        this.document.set(updated);
        this.acting.set(false);
      },
      error: (error: unknown) => {
        this.notice.set(
          error instanceof AppError ? error.message : 'La accion no pudo completarse.',
        );
        this.acting.set(false);
      },
    });
  }
}
