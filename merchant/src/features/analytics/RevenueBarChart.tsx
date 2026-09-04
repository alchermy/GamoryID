import { useEffect, useRef } from "react";
import {
  BarController,
  BarElement,
  CategoryScale,
  Chart,
  LinearScale,
  Tooltip,
} from "chart.js";
import { money } from "../../shared/lib/format";

Chart.register(BarController, BarElement, CategoryScale, LinearScale, Tooltip);

const compact = new Intl.NumberFormat("th-TH", {
  notation: "compact",
  maximumFractionDigits: 1,
});

type Props = {
  labels: string[];
  values: number[];
  /** Optional per-bar sale count, shown in the tooltip. */
  counts?: number[];
};

/** Horizontal revenue-by-category bar chart (Chart.js). */
export function RevenueBarChart({ labels, values, counts }: Props) {
  const canvasRef = useRef<HTMLCanvasElement>(null);
  const chartRef = useRef<Chart | null>(null);

  useEffect(() => {
    const canvas = canvasRef.current;
    const ctx = canvas?.getContext("2d");
    if (!canvas || !ctx) return; // jsdom / no 2d context

    chartRef.current?.destroy();
    chartRef.current = new Chart(ctx, {
      type: "bar",
      data: {
        labels,
        datasets: [
          {
            data: values,
            backgroundColor: "#0b6bff",
            hoverBackgroundColor: "#00c2ff",
            borderRadius: 5,
            maxBarThickness: 26,
          },
        ],
      },
      options: {
        indexAxis: "y",
        responsive: true,
        maintainAspectRatio: false,
        layout: { padding: { right: 8 } },
        plugins: {
          legend: { display: false },
          tooltip: {
            callbacks: {
              label: (item) => {
                const n = counts?.[item.dataIndex];
                const base = money.format(Number(item.raw) || 0);
                return n != null
                  ? `${base} · ${n.toLocaleString("th-TH")} รายการ`
                  : base;
              },
            },
          },
        },
        scales: {
          x: {
            beginAtZero: true,
            grid: { color: "#eef2f7" },
            ticks: {
              color: "#64758b",
              font: { size: 11 },
              callback: (value) => compact.format(Number(value)),
            },
          },
          y: {
            grid: { display: false },
            ticks: { color: "#40536b", font: { size: 11 } },
          },
        },
      },
    });

    return () => {
      chartRef.current?.destroy();
      chartRef.current = null;
    };
  }, [labels, values, counts]);

  return (
    <div className="analytics-chart">
      <canvas ref={canvasRef} role="img" aria-label="กราฟยอดขายรายหมวด" />
    </div>
  );
}
