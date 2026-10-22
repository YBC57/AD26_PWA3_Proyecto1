<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, onUnmounted } from 'vue';
import { dashboard } from '@/routes';

type Evento = {
    id: number;
    estado_puerta: string | null;
    estado_candado: string | null;
    vibracion_detectada: boolean;
    cantidad_vibracion: number | null;
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

type Puerta = {
    id: number;
    nombre: string;
    activa: boolean;
};

type Estadisticas = {
    total: number;
    abiertas: number;
    cerradas_sin_seguro: number;
    cerradas_con_seguro: number;
    vibraciones: number;
    llamados: number;
    posibles_forzamientos: number;
    lecturas_anomalas: number;
};

const props = defineProps<{
    eventos?: Paginacion;
    titulo?: string;
    puertas?: Puerta[];
    puertaSeleccionada?: Puerta;
    estadisticas?: Estadisticas;
    ultimoEvento?: Evento | null;
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
const estadisticas = computed<Estadisticas>(
    () =>
        props.estadisticas ?? {
            total: 0,
            abiertas: 0,
            cerradas_sin_seguro: 0,
            cerradas_con_seguro: 0,
            vibraciones: 0,
            llamados: 0,
            posibles_forzamientos: 0,
            lecturas_anomalas: 0,
        },
);

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

const etiquetaPuerta = (estado: string | null, tipoEvento?: string) => {
    if (tipoEvento === 'lectura_anomala')
        return 'Indeterminada (lectura anómala)';

    const mapa: Record<string, string> = {
        abierta: 'Abierta',
        cerrada: 'Cerrada',
    };

    return mapa[estado] ?? 'Indeterminada';
};

const etiquetaCandado = (estado: string | null, tipoEvento?: string) => {
    if (tipoEvento === 'lectura_anomala') return 'Indeterminado';

    const mapa: Record<string, string> = {
        bloqueado: 'Con seguro',
        desbloqueado: 'Sin seguro',
    };

    return mapa[estado] ?? 'Indeterminado';
};

const etiquetaEvento = (tipo: string) => {
    const mapa: Record<string, string> = {
        puerta_abierta: 'Puerta abierta',
        puerta_cerrada: 'Puerta cerrada sin seguro',
        candado_activado: 'Puerta cerrada con seguro',
        candado_desactivado: 'Puerta cerrada sin seguro',
        vibracion_suave: 'Alguien toca o llama a la puerta',
        vibracion_fuerte: 'Vibración fuerte (clasificación pendiente)',
        intento_forzado: 'Posible intento de forzar la puerta',
        puerta_cerrada_sin_seguro: 'Puerta cerrada sin seguro',
        puerta_cerrada_con_seguro: 'Puerta cerrada con seguro',
        llamado_puerta: 'Llamado o golpe suave',
        golpe_suave: 'Llamado o golpe suave',
        posible_forzamiento: 'Posible intento de forzamiento',
        lectura_anomala: 'Lectura anómala del sensor',
    };

    return mapa[tipo] ?? tipo;
};

const cambiarPuerta = (event: Event) => {
    const select = event.currentTarget as HTMLSelectElement;

    router.get(
        '/eventos-puerta',
        { puerta_id: select.value },
        { preserveState: true, preserveScroll: true },
    );
};

let cancelPollRequest: (() => void) | undefined;

const eventPoll = router.poll(
    3000,
    {
        only: ['eventos', 'estadisticas', 'ultimoEvento'],
        onCancelToken: ({ cancel }) => {
            cancelPollRequest = cancel;
        },
        onFinish: () => {
            cancelPollRequest = undefined;
        },
    },
    {
        autoStart: false,
        keepAlive: false,
        mode: 'rest',
    },
);

eventPoll.start();

onUnmounted(() => {
    eventPoll.destroy();
    cancelPollRequest?.();
});
</script>

<template>
    <Head :title="titulo || 'Dashboard'" />

    <div
        class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4"
    >
        <div class="flex items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-semibold text-foreground">
                    {{ titulo || 'Monitoreo de puerta' }}
                </h1>
                <p class="text-sm text-muted-foreground">
                    Historial de {{ puertaSeleccionada?.nombre || 'puerta' }}.
                </p>
            </div>
            <label
                class="flex items-center gap-2 text-sm text-muted-foreground"
            >
                <span>Puerta</span>
                <select
                    v-if="puertas?.length"
                    :value="puertaSeleccionada?.id"
                    class="rounded-md border border-border bg-background px-3 py-2 text-foreground"
                    aria-label="Seleccionar puerta"
                    @change="cambiarPuerta"
                >
                    <option
                        v-for="puerta in puertas"
                        :key="puerta.id"
                        :value="puerta.id"
                    >
                        {{ puerta.nombre
                        }}{{ puerta.activa ? '' : ' (inactiva)' }}
                    </option>
                </select>
            </label>
        </div>

        <p
            class="rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-200"
        >
            Los registros actuales son datos de prueba; no hay sensores físicos
            conectados. Las vibraciones no determinan por sí solas un intento de
            forzamiento.
        </p>

        <div class="rounded-xl border border-border bg-card p-4 shadow-sm">
            <h2 class="text-sm font-semibold text-muted-foreground">
                Último estado registrado
            </h2>
            <p v-if="ultimoEvento" class="mt-2 text-foreground">
                {{
                    etiquetaPuerta(
                        ultimoEvento.estado_puerta,
                        ultimoEvento.tipo_evento,
                    )
                }},
                {{
                    etiquetaCandado(
                        ultimoEvento.estado_candado,
                        ultimoEvento.tipo_evento,
                    )
                }}
                <span class="text-muted-foreground">
                    · {{ etiquetaEvento(ultimoEvento.tipo_evento) }} ·
                    {{ formatFecha(ultimoEvento.detectado_en) }}
                </span>
            </p>
            <p v-else class="mt-2 text-muted-foreground">
                Sin eventos para esta puerta.
            </p>
            <p class="mt-1 text-xs text-muted-foreground">
                Estado derivado del historial; no es una lectura en tiempo real.
            </p>
        </div>

        <div class="grid auto-rows-min gap-4 md:grid-cols-4">
            <div class="rounded-xl border border-border bg-card p-4 shadow-sm">
                <p class="text-sm text-muted-foreground">Total de eventos</p>
                <p class="mt-2 text-2xl font-bold text-foreground">
                    {{ estadisticas.total }}
                </p>
            </div>
            <div class="rounded-xl border border-border bg-card p-4 shadow-sm">
                <p class="text-sm text-muted-foreground">
                    Eventos de puerta abierta
                </p>
                <p class="mt-2 text-2xl font-bold text-foreground">
                    {{ estadisticas.abiertas }}
                </p>
            </div>
            <div class="rounded-xl border border-border bg-card p-4 shadow-sm">
                <p class="text-sm text-muted-foreground">Cerradas sin seguro</p>
                <p class="mt-2 text-2xl font-bold text-foreground">
                    {{ estadisticas.cerradas_sin_seguro }}
                </p>
            </div>
            <div class="rounded-xl border border-border bg-card p-4 shadow-sm">
                <p class="text-sm text-muted-foreground">Cerradas con seguro</p>
                <p class="mt-2 text-2xl font-bold text-foreground">
                    {{ estadisticas.cerradas_con_seguro }}
                </p>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-xl border border-border bg-card p-4 shadow-sm">
                <p class="text-sm text-muted-foreground">
                    Llamados o golpes suaves
                </p>
                <p class="mt-2 text-2xl font-bold text-foreground">
                    {{ estadisticas.llamados }}
                </p>
            </div>
            <div class="rounded-xl border border-border bg-card p-4 shadow-sm">
                <p class="text-sm text-muted-foreground">
                    Posibles forzamientos
                </p>
                <p class="mt-2 text-2xl font-bold text-foreground">
                    {{ estadisticas.posibles_forzamientos }}
                </p>
            </div>
            <div class="rounded-xl border border-border bg-card p-4 shadow-sm">
                <p class="text-sm text-muted-foreground">Lecturas anómalas</p>
                <p class="mt-2 text-2xl font-bold text-foreground">
                    {{ estadisticas.lecturas_anomalas }}
                </p>
            </div>
            <div class="rounded-xl border border-border bg-card p-4 shadow-sm">
                <p class="text-sm text-muted-foreground">
                    Eventos con vibración
                </p>
                <p class="mt-2 text-2xl font-bold text-foreground">
                    {{ estadisticas.vibraciones }}
                </p>
            </div>
        </div>

        <div class="rounded-xl border border-border bg-card shadow-sm">
            <div
                class="flex items-center justify-between border-b border-border px-4 py-3"
            >
                <h2 class="text-lg font-semibold text-foreground">
                    Eventos recientes
                </h2>
                <span
                    class="rounded-full bg-muted px-2 py-1 text-xs font-medium text-muted-foreground"
                >
                    Estadísticas de todos los eventos de esta puerta
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
                            <th class="px-4 py-3 font-medium">
                                Activaciones
                            </th>
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
                                        evento.tipo_evento === 'lectura_anomala'
                                            ? 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300'
                                            : evento.estado_puerta === 'abierta'
                                              ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300'
                                              : 'bg-slate-200 text-slate-700 dark:bg-slate-800 dark:text-slate-200'
                                    "
                                >
                                    {{
                                        etiquetaPuerta(
                                            evento.estado_puerta,
                                            evento.tipo_evento,
                                        )
                                    }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                {{
                                    etiquetaCandado(
                                        evento.estado_candado,
                                        evento.tipo_evento,
                                    )
                                }}
                            </td>
                            <td class="px-4 py-3">
                                {{ etiquetaEvento(evento.tipo_evento) }}
                            </td>
                            <td class="px-4 py-3">
                                {{ evento.vibracion_detectada ? 'Sí' : 'No' }}
                            </td>
                            <td class="px-4 py-3">
                                {{ evento.cantidad_vibracion ?? 'No disponible' }}
                            </td>
                            <td class="px-4 py-3">
                                {{ formatFecha(evento.detectado_en) }}
                            </td>
                        </tr>
                        <tr v-if="!eventos.length">
                            <td
                                colspan="7"
                                class="px-4 py-8 text-center text-muted-foreground"
                            >
                                No hay eventos registrados todavía.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div
                v-if="links.length"
                class="flex flex-wrap items-center justify-center gap-2 border-t border-border px-4 py-3"
            >
                <template
                    v-for="link in links"
                    :key="link.label + (link.url ?? 'first')"
                >
                    <a
                        v-if="link.url"
                        :href="link.url"
                        class="rounded-md border border-border px-3 py-1.5 text-sm"
                        :class="
                            link.active
                                ? 'bg-primary text-primary-foreground'
                                : 'bg-background text-foreground hover:bg-muted'
                        "
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
