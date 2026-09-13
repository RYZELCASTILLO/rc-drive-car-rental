// ==========================================================
// RC DRIVE - MAIN JAVASCRIPT
// ==========================================================

"use strict";

const activeUser = window.RCDriveUser || null;

// Booking rules — MUST match config.php constants
const MAX_RENTAL_DAYS = 7;
const MIN_RENTAL_DAYS = 1;


document.addEventListener("DOMContentLoaded", function () {
    console.log("RC Drive JavaScript loaded successfully.");
    setupDates();
    setupVehicleFilters();
    setupRentForm();
});

// ==========================================================
// SETUP DATES
// ==========================================================

function setupDates() {
    const today = new Date().toISOString().split("T")[0];

    const startDate = document.getElementById("rentStartDate");
    const endDate   = document.getElementById("rentEndDate");

    if (startDate) startDate.min = today;
    if (endDate)   endDate.min   = today;

    if (startDate) {
        startDate.addEventListener("change", function () {
            if (endDate) {
                endDate.min = startDate.value;
                if (endDate.value && endDate.value < startDate.value) {
                    endDate.value = startDate.value;
                }
            }
            calculateBookingCost();
            refreshAvailabilityOnDateChange();
        });
    }

    if (endDate) {
        endDate.addEventListener("change", function () {
            calculateBookingCost();
        });
    }
}

// ==========================================================
// VEHICLE FILTER
// ==========================================================

function setupVehicleFilters() {
    const buttons = document.querySelectorAll(".filter-btn");
    const cards   = document.querySelectorAll(".car-card");

    buttons.forEach(function (button) {
        button.addEventListener("click", function () {

            buttons.forEach(function (btn) {
                btn.classList.remove("active");
            });

            button.classList.add("active");

            const filter = button.getAttribute("data-filter");

            cards.forEach(function (card) {
                if (filter === "all" || card.classList.contains(filter)) {
                    card.style.display = "";
                } else {
                    card.style.display = "none";
                }
            });
        });
    });
}

// ==========================================================
// OPEN RENT MODAL
// ==========================================================

function openRentModal(carName, price, totalUnits) {

    console.log("Rent button clicked:", carName, price, totalUnits);

    if (!activeUser) {
        alert("Please login or register before renting a vehicle.");
        const loginModalElement = document.getElementById("loginModal");
        if (loginModalElement) {
            const loginModal = bootstrap.Modal.getOrCreateInstance(loginModalElement);
            loginModal.show();
        }
        return;
    }

    const modal        = document.getElementById("rentModal");
    const title        = document.getElementById("modalVehicleTitle");
    const rateText     = document.getElementById("modalVehicleRate");
    const vehicleName  = document.getElementById("selectedCarName");
    const vehicleRate  = document.getElementById("selectedCarRate");
    const customerName = document.getElementById("rentCustomerName");

    if (!modal) {
        console.error("rentModal was not found.");
        return;
    }

    if (title)        title.textContent = "Rent " + carName;
    if (rateText)     rateText.textContent = "₱" + Number(price).toLocaleString("en-PH") + " per day";
    if (vehicleName)  vehicleName.value = carName;
    if (vehicleRate)  vehicleRate.value = price;
    if (customerName && activeUser) customerName.value = activeUser.name || "";

    const today     = new Date().toISOString().split("T")[0];
    const startDate = document.getElementById("rentStartDate");
    const endDate   = document.getElementById("rentEndDate");

    if (startDate) {
        startDate.min   = today;
        startDate.value = today;
    }
    if (endDate) {
        endDate.min   = today;
        endDate.value = today;
    }

    calculateBookingCost();

    const rentModal = bootstrap.Modal.getOrCreateInstance(modal);
    rentModal.show();

    loadVehicleAvailability(carName);
}

// ==========================================================
// LOAD VEHICLE AVAILABILITY
// ==========================================================

async function loadVehicleAvailability(vehicleName) {

    const startDate = document.getElementById("rentStartDate");
    const endDate   = document.getElementById("rentEndDate");

    if (!startDate || !endDate) return;

    try {
        const response = await fetch(
            "vehicle_availability.php?vehicle=" + encodeURIComponent(vehicleName)
        );
        const data = await response.json();

        if (!data.success) {
            console.error(data.message || "Unable to load availability.");
            return;
        }

        startDate._unavailableDates   = data.unavailable_dates || [];
        endDate._unavailableDates     = data.unavailable_dates || [];

        startDate._totalStock         = data.total_stock || 0;
        startDate._currentlyAvailable = data.currently_available || 0;
        endDate._totalStock           = data.total_stock || 0;
        endDate._currentlyAvailable   = data.currently_available || 0;

        console.log("Availability loaded for " + vehicleName + ":", {
            total_stock: data.total_stock,
            currently_available: data.currently_available,
            unavailable_dates: data.unavailable_dates
        });

    } catch (error) {
        console.error("Vehicle availability error:", error);
    }
}

function refreshAvailabilityOnDateChange() {
    // No-op for now
}

// ==========================================================
// RENT FORM
// ==========================================================

function setupRentForm() {
    const form = document.getElementById("rentForm");
    if (!form) return;

    form.addEventListener("submit", function (event) {

        if (!activeUser) {
            event.preventDefault();
            alert("Please login before making a booking.");
            return;
        }

        const vehicle = document.getElementById("selectedCarName");
        if (!vehicle || !vehicle.value) {
            event.preventDefault();
            alert("Please select a vehicle.");
            return;
        }

        const phone = document.getElementById("rentPhone");
        if (phone && !phone.checkValidity()) {
            event.preventDefault();
            phone.reportValidity();
            return;
        }

        const license = document.getElementById("rentDriverLicense");
        if (license && license.value.trim().length < 5) {
            event.preventDefault();
            alert("Please enter a valid driver's license number.");
            license.focus();
            return;
        }

        const start = document.getElementById("rentStartDate");
        const end   = document.getElementById("rentEndDate");

        if (!start || !end || !start.value || !end.value) {
            event.preventDefault();
            alert("Please select both rental dates.");
            return;
        }

        if (end.value < start.value) {
            event.preventDefault();
            alert("Return date cannot be earlier than the pickup date.");
            return;
        }

        const daysDiff = calculateDaysBetween(start.value, end.value);

        if (daysDiff > MAX_RENTAL_DAYS) {
            event.preventDefault();
            alert(
                "Rentals are limited to a maximum of " + MAX_RENTAL_DAYS + " day(s).\n\n" +
                "Your selected range is " + daysDiff + " day(s).\n" +
                "Please adjust the return date."
            );
            return;
        }

        const totalStock         = start._totalStock || 0;
        const currentlyAvailable = start._currentlyAvailable;

        if (
            typeof currentlyAvailable !== "undefined" &&
            totalStock > 0 &&
            currentlyAvailable <= 0
        ) {
            event.preventDefault();
            alert(
                "Sorry, the " + vehicle.value + " has no available units right now.\n\n" +
                "All " + totalStock + " unit(s) are already booked. " +
                "Please choose a different vehicle or try again later."
            );
            return;
        }

        const unavailable = start._unavailableDates || [];

        if (unavailable.length > 0) {
            const current       = new Date(start.value + "T00:00:00");
            const last          = new Date(end.value + "T00:00:00");
            const conflictDates = [];

            while (current <= last) {
                const key = current.toISOString().split("T")[0];
                if (unavailable.includes(key)) {
                    conflictDates.push(key);
                }
                current.setDate(current.getDate() + 1);
            }

            if (conflictDates.length > 0) {
                event.preventDefault();

                const list = conflictDates.join("\n• ");

                alert(
                    "Sorry, the " + vehicle.value + " is fully booked on the following date(s):\n\n" +
                    "• " + list + "\n\n" +
                    "Please select different dates."
                );
                return;
            }
        }

        const total = document.getElementById("calculatedTotal");
        const confirmed = confirm(
            "Submit this vehicle rental request?\n\n" +
            "Vehicle: " + vehicle.value + "\n" +
            "Total: " + (total ? total.textContent : "")
        );

        if (!confirmed) {
            event.preventDefault();
            return;
        }

        console.log("Rental form submitted.");
    });
}

// ==========================================================
// CALCULATE DAYS BETWEEN
// ==========================================================

function calculateDaysBetween(startDateString, endDateString) {

    if (!startDateString || !endDateString) return 1;

    const start = new Date(startDateString + "T00:00:00");
    const end   = new Date(endDateString + "T00:00:00");

    const difference = end.getTime() - start.getTime();
    const days       = Math.ceil(difference / (1000 * 60 * 60 * 24));

    return days < 1 ? 1 : days;
}

// ==========================================================
// CALCULATE BOOKING COST
// ==========================================================

function calculateBookingCost() {

    const rateInput   = document.getElementById("selectedCarRate");
    const startInput  = document.getElementById("rentStartDate");
    const endInput    = document.getElementById("rentEndDate");
    const daysOutput  = document.getElementById("calculatedDays");
    const totalOutput = document.getElementById("calculatedTotal");

    if (!rateInput) return;

    const rate  = parseFloat(rateInput.value) || 0;
    const start = startInput ? startInput.value : "";
    const end   = endInput   ? endInput.value   : "";

    const days  = calculateDaysBetween(start, end);
    const total = rate * days;

    if (daysOutput) {
        if (days > MAX_RENTAL_DAYS) {
            daysOutput.textContent = days + " Days (max " + MAX_RENTAL_DAYS + ")";
            daysOutput.style.color = "#ff5560";
        } else {
            daysOutput.textContent = days + (days === 1 ? " Day" : " Days");
            daysOutput.style.color = "";
        }
    }

    if (totalOutput) {
        totalOutput.textContent = "₱" + total.toLocaleString("en-PH", {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }
}

// ==========================================================
// DEBUG
// ==========================================================

console.log("RC Drive script.js is ready.");