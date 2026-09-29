<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import { dashboard } from '@/routes';

type Evento = {
    id: number;
    estado_puerta: string;
    estado_candado: string;
    vibracion_detectada: boolean;
    cantidad_vibracion: number;
    tipo_evento: string;
    detectado_en: string | null;
    created_at: string | null;
};

type Paginacion = {
    data?: Evento[];
    links?: Array<{ url: string | null; label: string; active: boolean }>;
    current_page?: number;
    last_page?: number;
};

const props = defineProps<{
    eventos?: Paginacion;
    titulo?: string;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
        ],
    },
});

const eventos = computed<Evento[]>(() => props.eventos?.data ?? []);
const links = computed(() => props.eventos?.links ?? []);

const formatFecha = (valor: string | null | undefined) => {
    if (!valor) return 'Sin fecha';

    const fecha = new Date(valor);

    if (Number.isNaN(fecha.getTime())) {
        return 'Sin fecha';
    }

    return new Intl.DateTimeFormat('es-ES', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(fecha);
};

const etiquetaPuerta = (estado: string) => {
    const mapa: Record<string, string> = {
        abierta: 'Abierta',
        cerrada: 'Cerrada',
    };

    return mapa[estado] ?? estado;
};

const etiquetaCandado = (estado: string) => {
    const mapa: Record<string, string> = {
        bloqueado: 'Con seguro',
        desbloqueado: 'Sin seguro',
    };

    return mapa[estado] ?? estado;
};

const etiquetaEvento = (tipo: string) => {
    const mapa: Record<string, string> = {
        puerta_abierta: 'Puerta abierta',
        puerta_cerrada: 'Puerta cerrada',
        candado_activado: 'Seguro activado',
        candado_desactivado: 'Seguro desactivado',
        vibracion_suave: 'Alguien toca o llama a la puerta',
        vibracion_fuerte: 'Vibración detectada',
        intento_forzado: 'Posible intento de forzar la puerta',
    };

    return mapa[tipo] ?? tipo;
};

const numeroEventos = computed(() => eventos.value.length);
const numeroAbiertas = computed(
    () => eventos.value.filter((evento) => evento.estado_puerta === 'abierta').length,
);
const numeroCerradasSinSeguro = computed(
    () =>
        eventos.value.filter(
            (evento) => evento.estado_puerta === 'cerrada' && evento.estado_candado === 'desbloqueado',
        ).length,
);
const numeroCerradasConSeguro = computed(
    () =>
        eventos.value.filter(
            (evento) => evento.estado_puerta === 'cerrada' && evento.estado_candado === 'bloqueado',
        ).length,
);
const numeroVibraciones = computed(
    () => eventos.value.filter((evento) => evento.vibracion_detectada).length,
);
</script>

<template>
    <Head :title="titulo || 'Dashboard'" />

    <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
        <div class="flex items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold text-foreground">
                    {{ titulo || 'Monitoreo de puerta' }}
                </h1>
                <p class="text-sm text-muted-foreground">
                    Registro de eventos del sistema de monitoreo.
                </p>
            </div>
        </div>

        <div class="grid auto-rows-min gap-4 md:grid-cols-4">
            <div class="rounded-xl border border-border bg-card p-4 shadow-sm">
                <p class="text-sm text-muted-foreground">Total de eventos</p>
                <p class="mt-2 text-2xl font-bold text-foreground">{{ numeroEventos }}</p>
            </div>
            <div class="rounded-xl border border-border bg-card p-4 shadow-sm">
                <p class="text-sm text-muted-foreground">Puertas abiertas</p>
                <p class="mt-2 text-2xl font-bold text-foreground">{{ numeroAbiertas }}</p>
            </div>
            <div class="rounded-xl border border-border bg-card p-4 shadow-sm">
                <p class="text-sm text-muted-foreground">Cerradas sin seguro</p>
                <p class="mt-2 text-2xl font-bold text-foreground">
                    {{ numeroCerradasSinSeguro }}
                </p>
            </div>
            <div class="rounded-xl border border-border bg-card p-4 shadow-sm">
                <p class="text-sm text-muted-foreground">Cerradas con seguro</p>
                <p class="mt-2 text-2xl font-bold text-foreground">
                    {{ numeroCerradasConSeguro }}
                </p>
            </div>
        </div>

        <div class="rounded-xl border border-border bg-card shadow-sm">
            <div class="flex items-center justify-between border-b border-border px-4 py-3">
                <h2 class="text-lg font-semibold text-foreground">Eventos recientes</h2>
                <span class="rounded-full bg-muted px-2 py-1 text-xs font-medium text-muted-foreground">
                    Vibración detectada: {{ numeroVibraciones }}
                </span>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-muted/40 text-muted-foreground">
                        <tr>
                            <th class="px-4 py-3 font-medium">ID</th>
                            <th class="px-4 py-3 font-medium">Puerta</th>
                            <th class="px-4 py-3 font-medium">Seguro</th>
                            <th class="px-4 py-3 font-medium">Evento</th>
                            <th class="px-4 py-3 font-medium">Vibración</th>
                            <th class="px-4 py-3 font-medium">Cantidad</th>
                            <th class="px-4 py-3 font-medium">Fecha / hora</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="evento in eventos"
                            :key="evento.id"
                            class="border-t border-border text-foreground"
                        >
                            <td class="px-4 py-3">{{ evento.id }}</td>
                            <td class="px-4 py-3">
                                <span
                                    class="rounded-full px-2 py-1 text-xs font-medium"
                                    :class="
                                        evento.estado_puerta === 'abierta'
                                            ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300'
                                            : 'bg-slate-200 text-slate-700 dark:bg-slate-800 dark:text-slate-200'
                                    "
                                >
                                    {{ etiquetaPuerta(evento.estado_puerta) }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                {{ etiquetaCandado(evento.estado_candado) }}
                            </td>
                            <td class="px-4 py-3">{{ etiquetaEvento(evento.tipo_evento) }}</td>
                            <td class="px-4 py-3">
                                {{ evento.vibracion_detectada ? 'Sí' : 'No' }}
                            </td>
                            <td class="px-4 py-3">
                                {{ evento.cantidad_vibracion ?? 0 }}
                            </td>
                            <td class="px-4 py-3">{{ formatFecha(evento.detectado_en) }}</td>
                        </tr>
                        <tr v-if="!eventos.length">
                            <td colspan="7" class="px-4 py-8 text-center text-muted-foreground">
                                No hay eventos registrados todavía.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="links.length" class="flex flex-wrap items-center justify-center gap-2 border-t border-border px-4 py-3">
                <template v-for="link in links" :key="link.label + (link.url ?? 'first')">
                    <a
                        v-if="link.url"
                        :href="link.url"
                        class="rounded-md border border-border px-3 py-1.5 text-sm"
                        :class="link.active ? 'bg-primary text-primary-foreground' : 'bg-background text-foreground hover:bg-muted'"
                        v-html="link.label"
                    />
                    <span
                        v-else
                        class="cursor-default rounded-md border border-border bg-muted px-3 py-1.5 text-sm text-muted-foreground"
                        v-html="link.label"
                    />
                </template>
            </div>
        </div>
    </div>
</template>
