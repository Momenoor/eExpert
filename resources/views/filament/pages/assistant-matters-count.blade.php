<x-report-layout>
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        @foreach ($this->getHeaderWidgets() as $widget)
            @livewire($widget)
        @endforeach
    </div>

    <script>
        document.addEventListener('livewire:init', () => {

            const attach = () => {
                document.querySelectorAll('canvas').forEach(canvas => {

                    if (canvas.dataset.listenerAttached) return;

                    const chart = canvas._x_dataStack?.[0]?.chart;
                    console.log(chart);
                    if (!chart) return;

                    canvas.addEventListener('click', (event) => {

                        const points = chart.getElementsAtEventForMode(
                            event,
                            'nearest',
                            { intersect: true },
                            true
                        );

                        if (!points.length) return;

                        const index = points[0].index;
                        const dataset = chart.data.datasets[0];

                        const partyId = dataset.ids?.[index];
                        const label = chart.data.labels[index];

                        if (!partyId) return;

                        window.Livewire.dispatch('filterTableByAssistant', {
                            partyId,
                            assistantName: label
                        });
                    });

                    canvas.style.cursor = 'pointer';
                    canvas.dataset.listenerAttached = 'true';
                });
            };

            // run multiple times safely
            attach();
            setTimeout(attach, 500);
            setTimeout(attach, 1000);

            Livewire.hook('morph.updated', attach);
        });
    </script>
</x-report-layout>
