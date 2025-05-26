// Custom JavaScript for Groupe IKI

document.addEventListener("DOMContentLoaded", () => {
  // Initialize all components
  initializeLoginForm()
  initializeUserManagement()
  initializeNavigation()
  initializeFormValidation()
  initializePasswordToggle()
  initializeLoadingStates()

  // Login Form Functionality
  function initializeLoginForm() {
    const loginForm = document.getElementById("loginForm")
    const errorAlert = document.getElementById("error-alert")

    if (loginForm) {
      // Clear error alerts when user starts typing
      const inputs = loginForm.querySelectorAll("input")
      inputs.forEach((input) => {
        input.addEventListener("input", function () {
          if (errorAlert) {
            errorAlert.style.display = "none"
          }
          // Remove invalid class
          this.classList.remove("is-invalid")
        })
      })

      // Form submission with validation
      loginForm.addEventListener("submit", (e) => {
        if (!validateLoginForm()) {
          e.preventDefault()
          return false
        }

        // Show loading state
        showButtonLoading("loginBtn")
      })
    }
  }

  // User Management Form Functionality
  function initializeUserManagement() {
    const roleSelect = document.getElementById("role")
    const addUserForm = document.getElementById("addUserForm")

    if (roleSelect) {
      // Handle role change to show/hide specific fields
      roleSelect.addEventListener("change", function () {
        toggleRoleSpecificFields(this.value)
      })
    }

    if (addUserForm) {
      // Form submission with validation
      addUserForm.addEventListener("submit", (e) => {
        if (!validateAddUserForm()) {
          e.preventDefault()
          return false
        }

        // Show loading state
        showButtonLoading("submitBtn")
      })

      // Reset form functionality
      const resetBtn = addUserForm.querySelector('button[type="reset"]')
      if (resetBtn) {
        resetBtn.addEventListener("click", () => {
          // Hide all role-specific fields
          toggleRoleSpecificFields("")
          // Clear all validation states
          clearFormValidation(addUserForm)
        })
      }
    }
  }

  // Navigation and Sidebar
  function initializeNavigation() {
    // Highlight active navigation link
    highlightActiveNavLink()

    // Mobile sidebar toggle
    const sidebarToggle = document.querySelector('[data-bs-target="#sidebar"]')
    if (sidebarToggle) {
      sidebarToggle.addEventListener("click", () => {
        const sidebar = document.getElementById("sidebar")
        if (sidebar) {
          sidebar.classList.toggle("show")
        }
      })
    }
  }

  // Form Validation
  function initializeFormValidation() {
    // Add real-time validation to all forms
    const forms = document.querySelectorAll("form[novalidate]")
    forms.forEach((form) => {
      const inputs = form.querySelectorAll("input[required], select[required]")
      inputs.forEach((input) => {
        input.addEventListener("blur", function () {
          validateField(this)
        })

        input.addEventListener("input", function () {
          if (this.classList.contains("is-invalid")) {
            validateField(this)
          }
        })
      })
    })
  }

  // Password Toggle Functionality
  function initializePasswordToggle() {
    const togglePassword = document.getElementById("togglePassword")
    const passwordField = document.getElementById("password")

    if (togglePassword && passwordField) {
      togglePassword.addEventListener("click", function () {
        const type = passwordField.getAttribute("type") === "password" ? "text" : "password"
        passwordField.setAttribute("type", type)

        // Toggle icon
        const icon = this.querySelector("i")
        if (icon) {
          icon.classList.toggle("fa-eye")
          icon.classList.toggle("fa-eye-slash")
        }
      })
    }
  }

  // Loading States
  function initializeLoadingStates() {
    // Auto-hide alerts after 5 seconds
    const alerts = document.querySelectorAll(".alert:not(.alert-permanent)")
    alerts.forEach((alert) => {
      setTimeout(() => {
        if (alert && alert.parentNode) {
          alert.style.opacity = "0"
          setTimeout(() => {
            if (alert.parentNode) {
              alert.parentNode.removeChild(alert)
            }
          }, 300)
        }
      }, 5000)
    })
  }

  // Helper Functions

  function validateLoginForm() {
    const cni = document.getElementById("cni")
    const password = document.getElementById("password")
    let isValid = true

    if (!cni.value.trim()) {
      showFieldError(cni, "Please enter your CNI.")
      isValid = false
    } else {
      clearFieldError(cni)
    }

    if (!password.value.trim()) {
      showFieldError(password, "Please enter your password.")
      isValid = false
    } else {
      clearFieldError(password)
    }

    return isValid
  }

  function validateAddUserForm() {
    const form = document.getElementById("addUserForm")
    const requiredFields = form.querySelectorAll("input[required], select[required]")
    let isValid = true

    requiredFields.forEach((field) => {
      if (!validateField(field)) {
        isValid = false
      }
    })

    return isValid
  }

  function validateField(field) {
    const value = field.value.trim()
    let isValid = true
    let errorMessage = ""

    // Check if required field is empty
    if (field.hasAttribute("required") && !value) {
      errorMessage = `Please enter ${field.labels[0]?.textContent.replace("*", "").trim() || "this field"}.`
      isValid = false
    }

    // Email validation
    if (field.type === "email" && value && !isValidEmail(value)) {
      errorMessage = "Please enter a valid email address."
      isValid = false
    }

    // CNI validation (basic)
    if (field.name === "cni" && value && value.length < 6) {
      errorMessage = "CNI must be at least 6 characters long."
      isValid = false
    }

    if (isValid) {
      clearFieldError(field)
    } else {
      showFieldError(field, errorMessage)
    }

    return isValid
  }

  function showFieldError(field, message) {
    field.classList.add("is-invalid")
    const feedback = field.parentNode.querySelector(".invalid-feedback")
    if (feedback) {
      feedback.textContent = message
    }
  }

  function clearFieldError(field) {
    field.classList.remove("is-invalid")
    field.classList.add("is-valid")
  }

  function clearFormValidation(form) {
    const fields = form.querySelectorAll(".is-invalid, .is-valid")
    fields.forEach((field) => {
      field.classList.remove("is-invalid", "is-valid")
    })
  }

  function toggleRoleSpecificFields(role) {
    const studentFields = document.getElementById("studentFields")
    const teacherFields = document.getElementById("teacherFields")

    // Hide all role-specific fields first
    if (studentFields) studentFields.style.display = "none"
    if (teacherFields) teacherFields.style.display = "none"

    // Show relevant fields based on role
    if (role === "student" && studentFields) {
      studentFields.style.display = "block"
      animateFieldsIn(studentFields)
    } else if (role === "teacher" && teacherFields) {
      teacherFields.style.display = "block"
      animateFieldsIn(teacherFields)
    }
  }

  function animateFieldsIn(element) {
    element.style.opacity = "0"
    element.style.transform = "translateY(20px)"

    setTimeout(() => {
      element.style.transition = "all 0.3s ease"
      element.style.opacity = "1"
      element.style.transform = "translateY(0)"
    }, 50)
  }

  function showButtonLoading(buttonId) {
    const button = document.getElementById(buttonId)
    if (button) {
      const btnText = button.querySelector(".btn-text")
      const btnLoading = button.querySelector(".btn-loading")

      if (btnText && btnLoading) {
        btnText.classList.add("d-none")
        btnLoading.classList.remove("d-none")
        button.disabled = true
      }
    }
  }

  function hideButtonLoading(buttonId) {
    const button = document.getElementById(buttonId)
    if (button) {
      const btnText = button.querySelector(".btn-text")
      const btnLoading = button.querySelector(".btn-loading")

      if (btnText && btnLoading) {
        btnText.classList.remove("d-none")
        btnLoading.classList.add("d-none")
        button.disabled = false
      }
    }
  }

  function highlightActiveNavLink() {
    const currentPage = window.location.pathname.split("/").pop()
    const navLinks = document.querySelectorAll(".sidebar .nav-link")

    navLinks.forEach((link) => {
      const href = link.getAttribute("href")
      if (href && href.includes(currentPage)) {
        link.classList.add("active")
      } else {
        link.classList.remove("active")
      }
    })
  }

  function isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
    return emailRegex.test(email)
  }

  // Utility function to show toast notifications
  function showToast(message, type = "info") {
    // Create toast element
    const toast = document.createElement("div")
    toast.className = `alert alert-${type} alert-dismissible fade show position-fixed`
    toast.style.cssText = "top: 20px; right: 20px; z-index: 9999; min-width: 300px;"
    toast.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `

    document.body.appendChild(toast)

    // Auto remove after 5 seconds
    setTimeout(() => {
      if (toast.parentNode) {
        toast.style.opacity = "0"
        setTimeout(() => {
          if (toast.parentNode) {
            toast.parentNode.removeChild(toast)
          }
        }, 300)
      }
    }, 5000)
  }

  // Keyboard shortcuts
  document.addEventListener("keydown", (e) => {
    // Ctrl/Cmd + / to focus search (if exists)
    if ((e.ctrlKey || e.metaKey) && e.key === "/") {
      e.preventDefault()
      const searchInput = document.querySelector('input[type="search"]')
      if (searchInput) {
        searchInput.focus()
      }
    }

    // Escape to close modals/dropdowns
    if (e.key === "Escape") {
      const openDropdowns = document.querySelectorAll(".dropdown-menu.show")
      openDropdowns.forEach((dropdown) => {
        const toggle = dropdown.previousElementSibling
        if (toggle) {
          const dropdownInstance = new Dropdown(toggle)
          dropdownInstance.hide()
        }
      })
    }
  })

  // Smooth scrolling for anchor links
  document.querySelectorAll('a[href^="#"]').forEach((anchor) => {
    anchor.addEventListener("click", function (e) {
      e.preventDefault()
      const target = document.querySelector(this.getAttribute("href"))
      if (target) {
        target.scrollIntoView({
          behavior: "smooth",
          block: "start",
        })
      }
    })
  })

  // Form auto-save (for longer forms)
  function initializeAutoSave() {
    const forms = document.querySelectorAll("form[data-autosave]")
    forms.forEach((form) => {
      const formId = form.id || "form_" + Date.now()

      // Load saved data
      loadFormData(form, formId)

      // Save on input
      form.addEventListener(
        "input",
        debounce(() => {
          saveFormData(form, formId)
        }, 1000),
      )
    })
  }

  function saveFormData(form, formId) {
    const formData = new FormData(form)
    const data = {}
    for (const [key, value] of formData.entries()) {
      data[key] = value
    }
    localStorage.setItem("form_" + formId, JSON.stringify(data))
  }

  function loadFormData(form, formId) {
    const savedData = localStorage.getItem("form_" + formId)
    if (savedData) {
      try {
        const data = JSON.parse(savedData)
        Object.keys(data).forEach((key) => {
          const field = form.querySelector(`[name="${key}"]`)
          if (field && field.type !== "password") {
            field.value = data[key]
          }
        })
      } catch (e) {
        console.warn("Failed to load saved form data:", e)
      }
    }
  }

  function debounce(func, wait) {
    let timeout
    return function executedFunction(...args) {
      const later = () => {
        clearTimeout(timeout)
        func(...args)
      }
      clearTimeout(timeout)
      timeout = setTimeout(later, wait)
    }
  }

  // Initialize auto-save if needed
  if (document.querySelector("form[data-autosave]")) {
    initializeAutoSave()
  }

  // Performance monitoring
  if ("performance" in window) {
    window.addEventListener("load", () => {
      setTimeout(() => {
        const perfData = performance.getEntriesByType("navigation")[0]
        if (perfData && perfData.loadEventEnd - perfData.loadEventStart > 3000) {
          console.warn("Page load time is slow:", perfData.loadEventEnd - perfData.loadEventStart, "ms")
        }
      }, 0)
    })
  }
})

// Global utility functions
window.GroupeIKI = {
  showToast: (message, type = "info") => {
    // Implementation moved to main scope above
  },

  validateForm: (formId) => {
    const form = document.getElementById(formId)
    if (!form) return false

    const requiredFields = form.querySelectorAll("input[required], select[required]")
    let isValid = true

    requiredFields.forEach((field) => {
      if (!field.value.trim()) {
        field.classList.add("is-invalid")
        isValid = false
      } else {
        field.classList.remove("is-invalid")
        field.classList.add("is-valid")
      }
    })

    return isValid
  },

  clearForm: (formId) => {
    const form = document.getElementById(formId)
    if (form) {
      form.reset()
      form.querySelectorAll(".is-invalid, .is-valid").forEach((field) => {
        field.classList.remove("is-invalid", "is-valid")
      })
    }
  },
}

// Dropdown class definition
class Dropdown {
  constructor(element) {
    this.element = element
  }

  hide() {
    this.element.classList.remove("show")
  }
}
