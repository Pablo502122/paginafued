/**
 * payment_status.js
 * Script de polling para consultar el estado del pago cada 5 segundos.
 * 
 * Lógica del contador:
 * - Inicia con changeCount = 0
 * - Cada 5 segundos consulta check_status.php
 * - Si el conekta_status cambió → incrementa changeCount y actualiza la UI
 * - Si NO hay cambios → NO ejecuta acción, solo repite
 * - Si el estatus es final (paid/declined/expired) → detiene el ciclo
 */

(function () {
    'use strict';

    // Obtener el ticket_id del atributo data del contenedor
    const statusContainer = document.getElementById('conekta-status-container');
    if (!statusContainer) return;

    const ticketId = statusContainer.dataset.ticketId;
    if (!ticketId) return;

    // Estado interno del polling
    let previousStatus = statusContainer.dataset.initialStatus || 'pending';
    let changeCount = 0;
    let pollInterval = null;
    let isPolling = false;

    // Elementos de la UI
    const statusBadge = document.getElementById('conekta-status-badge');
    const statusText = document.getElementById('conekta-status-text');
    const changeCounter = document.getElementById('change-counter');
    const pollingIndicator = document.getElementById('polling-indicator');
    const lastChecked = document.getElementById('last-checked');

    // Mapeo de estatus a colores y textos
    const statusConfig = {
        'pending': { class: 'status-pending', label: 'Pendiente', icon: '⏳' },
        'pending_payment': { class: 'status-pending', label: 'Pago Pendiente', icon: '⏳' },
        'paid': { class: 'status-paid', label: 'Aceptado / Pagado', icon: '✅' },
        'declined': { class: 'status-rejected', label: 'Rechazado', icon: '❌' },
        'expired': { class: 'status-rejected', label: 'Expirado', icon: '⏰' },
        'refunded': { class: 'status-refunded', label: 'Reembolsado', icon: '🔄' },
        'canceled': { class: 'status-rejected', label: 'Cancelado', icon: '🚫' },
        'voided': { class: 'status-rejected', label: 'Anulado', icon: '🚫' }
    };

    /**
     * Actualiza la UI con el nuevo estatus
     */
    function updateUI(data) {
        const config = statusConfig[data.conekta_status] || statusConfig['pending'];

        if (statusBadge) {
            statusBadge.className = 'conekta-badge ' + config.class;
            statusBadge.textContent = config.icon + ' ' + config.label;
        }

        if (statusText) {
            statusText.textContent = config.label;
        }

        if (changeCounter) {
            changeCounter.textContent = data.change_count;
        }

        if (lastChecked) {
            lastChecked.textContent = data.checked_at;
        }

        // Animar el badge cuando hay un cambio
        if (statusBadge) {
            statusBadge.classList.add('status-changed');
            setTimeout(() => statusBadge.classList.remove('status-changed'), 1000);
        }
    }

    /**
     * Muestra/oculta el indicador de polling
     */
    function setPollingActive(active) {
        if (pollingIndicator) {
            pollingIndicator.style.display = active ? 'inline-flex' : 'none';
        }
    }

    /**
     * Detiene el ciclo de polling
     */
    function stopPolling(reason) {
        if (pollInterval) {
            clearInterval(pollInterval);
            pollInterval = null;
        }
        isPolling = false;
        setPollingActive(false);

        if (pollingIndicator) {
            pollingIndicator.innerHTML = '<span class="poll-done">✔ Estatus final alcanzado</span>';
            pollingIndicator.style.display = 'inline-flex';
        }

        console.log('[PaymentStatus] Polling detenido:', reason);
    }

    /**
     * Consulta el estado del ticket al servidor
     */
    async function checkStatus() {
        try {
            const response = await fetch('check_status.php?ticket_id=' + ticketId, {
                method: 'GET',
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json' }
            });

            if (!response.ok) {
                console.error('[PaymentStatus] Error HTTP:', response.status);
                return;
            }

            const data = await response.json();

            // Verificar si el estatus cambió
            if (data.conekta_status !== previousStatus) {
                // ¡Hubo un cambio! Incrementar contador y ejecutar acción
                changeCount = data.change_count;
                previousStatus = data.conekta_status;

                console.log('[PaymentStatus] ¡Cambio detectado! Nuevo estatus:',
                    data.conekta_status, '| Cambios:', changeCount);

                // EJECUTAR ACCIÓN: Actualizar la UI
                updateUI(data);
            } else {
                // Sin cambios - NO ejecutar acción, solo repetir
                console.log('[PaymentStatus] Sin cambios. Repitiendo en 5s...');
            }

            // Si es estatus final, detener el polling
            if (data.is_final) {
                // Actualizar UI una última vez para asegurar estado final correcto
                updateUI(data);
                stopPolling('Estatus final: ' + data.conekta_status);
            }

        } catch (error) {
            console.error('[PaymentStatus] Error de red:', error);
        }
    }

    /**
     * Inicia el ciclo de polling cada 5 segundos
     */
    function startPolling() {
        if (isPolling) return;
        isPolling = true;
        setPollingActive(true);

        console.log('[PaymentStatus] Iniciando polling cada 5 segundos para ticket #' + ticketId);

        // Primera consulta inmediata
        checkStatus();

        // Repetir cada 5 segundos
        pollInterval = setInterval(checkStatus, 5000);
    }

    // Iniciar automáticamente cuando el DOM está listo
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', startPolling);
    } else {
        startPolling();
    }

})();
