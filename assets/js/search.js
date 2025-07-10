function initializeSearch(tableId, options = {}) {
  const table = document.getElementById(tableId);
  if (!table) return;

  const tbody = table.querySelector("tbody");
  const rows = Array.from(tbody.getElementsByTagName("tr"));

  // Create search and filter UI
  const container = document.createElement("div");
  container.className = "search-filter-container";

  // Search input group
  const searchGroup = document.createElement("div");
  searchGroup.className = "search-input-group";

  const searchIcon = document.createElement("i");
  searchIcon.className = "fas fa-search search-icon";

  const searchInput = document.createElement("input");
  searchInput.type = "text";
  searchInput.className = "search-input";
  searchInput.placeholder = options.searchPlaceholder || "Cari...";

  searchGroup.appendChild(searchIcon);
  searchGroup.appendChild(searchInput);

  // Filter group
  const filterGroup = document.createElement("div");
  filterGroup.className = "filter-group";

  if (options.filters) {
    options.filters.forEach((filter) => {
      const select = document.createElement("select");
      select.className = "filter-select";
      select.innerHTML = `<option value="">${filter.placeholder}</option>`;
      filter.options.forEach((opt) => {
        select.innerHTML += `<option value="${opt.value}">${opt.label}</option>`;
      });
      filterGroup.appendChild(select);
    });
  }

  container.appendChild(searchGroup);
  if (options.filters) {
    container.appendChild(filterGroup);
  }

  // Insert before the table
  table.parentNode.insertBefore(container, table);

  // Search and filter function
  function filterTable() {
    const searchTerm = searchInput.value.toLowerCase();
    const filterValues = {};
    if (options.filters) {
      options.filters.forEach((filter, index) => {
        filterValues[filter.field] = filterGroup.children[index].value;
      });
    }

    rows.forEach((row) => {
      let showRow = true;

      // Text search
      if (searchTerm) {
        const text = row.textContent.toLowerCase();
        showRow = text.includes(searchTerm);
      }

      // Apply filters
      if (showRow && options.filters) {
        showRow = options.filters.every((filter) => {
          const filterValue = filterValues[filter.field];
          if (!filterValue) return true;

          const cell = row.querySelector(`[data-label="${filter.field}"]`);
          return cell && cell.textContent.trim() === filterValue;
        });
      }

      row.style.display = showRow ? "" : "none";
    });

    // Show/hide empty state
    const visibleRows = rows.filter((row) => row.style.display !== "none");
    const emptyState = tbody.querySelector(".empty-state");
    if (visibleRows.length === 0) {
      if (!emptyState) {
        const emptyRow = document.createElement("tr");
        emptyRow.className = "empty-state";
        emptyRow.innerHTML = `
                    <td colspan="${
                      table.querySelectorAll("th").length
                    }" class="px-6 py-12 text-center text-gray-500">
                        <div class="flex flex-col items-center">
                            <i class="fas fa-search fa-3x text-gray-300"></i>
                            <p class="mt-4">Tidak ada data yang sesuai dengan pencarian.</p>
                        </div>
                    </td>
                `;
        tbody.appendChild(emptyRow);
      }
    } else if (emptyState) {
      emptyState.remove();
    }
  }

  // Event listeners
  searchInput.addEventListener("input", filterTable);
  if (options.filters) {
    filterGroup.querySelectorAll("select").forEach((select) => {
      select.addEventListener("change", filterTable);
    });
  }

  return { filterTable };
}
