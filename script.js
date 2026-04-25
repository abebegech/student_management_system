let currentPage = 1;
const TOTAL_PAGES = 10; // adjust if you change pagination logic server-side

// LOAD TABLE
function loadData(page = 1) {
  currentPage = page;

  fetch("fetch.php?page=" + page)
    .then((res) => res.text())
    .then((data) => {
      document.getElementById("table-data").innerHTML = data;
      highlightActivePage();
    });
}

// LOAD TOTAL
function loadTotal() {
  fetch("count.php")
    .then((res) => res.text())
    .then((data) => {
      const totalEl = document.getElementById("total");
      if (totalEl) {
        totalEl.innerText = data;
      }
    });
}

// LOAD CHART
function loadChart() {
  const canvas = document.getElementById("chart");
  if (!canvas) return;

  fetch("chart.php")
    .then((res) => res.json())
    .then((data) => {
      const labels = data.map((d) => d.department);
      const values = data.map((d) => d.total);

      new Chart(canvas, {
        type: "bar",
        data: {
          labels,
          datasets: [
            {
              label: "Students",
              data: values,
              backgroundColor: [
                "rgba(59,130,246,0.8)",
                "rgba(56,189,248,0.8)",
                "rgba(129,140,248,0.9)",
                "rgba(16,185,129,0.85)",
                "rgba(251,191,36,0.9)",
              ],
              borderRadius: 6,
            },
          ],
        },
        options: {
          plugins: {
            legend: {
              labels: {
                color: "#e5e7eb",
              },
            },
          },
          scales: {
            x: {
              ticks: { color: "#9ca3af" },
              grid: { display: false },
            },
            y: {
              ticks: { color: "#9ca3af", precision: 0 },
              grid: { color: "rgba(55,65,81,0.6)" },
            },
          },
        },
      });
    });
}

// PAGINATION BUTTONS
function createPagination() {
  let html = "";
  for (let i = 1; i <= TOTAL_PAGES; i++) {
    html += `<button data-page="${i}" onclick="loadData(${i})">${i}</button>`;
  }
  const container = document.getElementById("pagination");
  if (container) {
    container.innerHTML = html;
  }
  highlightActivePage();
}

function highlightActivePage() {
  const buttons = document.querySelectorAll("#pagination button");
  buttons.forEach((btn) => {
    const page = Number(btn.getAttribute("data-page"));
    btn.classList.toggle("active", page === currentPage);
  });
}

// SEARCH
function initSearch() {
  const searchInput = document.getElementById("search");
  if (!searchInput) return;

  searchInput.addEventListener("keyup", function () {
    const value = this.value.toLowerCase();
    const rows = document.querySelectorAll("#table-data tr");

    rows.forEach((row) => {
      row.style.display = row.innerText.toLowerCase().includes(value) ? "" : "none";
    });
  });
}

function confirmDelete() {
  return confirm("Delete this student?");
}

// Simple entrance animation
function revealOnLoad() {
  const animated = document.querySelectorAll(".animate-on-load");
  animated.forEach((el, index) => {
    setTimeout(() => {
      el.classList.add("is-visible");
    }, 120 + index * 90);
  });
}

// INIT
document.addEventListener("DOMContentLoaded", () => {
  const tableBody = document.getElementById("table-data");
  const pagination = document.getElementById("pagination");
  const totalEl = document.getElementById("total");
  const chartEl = document.getElementById("chart");
  const searchEl = document.getElementById("search");

  if (tableBody && pagination) {
    loadData();
    createPagination();
  }

  if (totalEl) {
    loadTotal();
  }

  if (chartEl) {
    loadChart();
  }

  if (searchEl) {
    initSearch();
  }

  revealOnLoad();
});