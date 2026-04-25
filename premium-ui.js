/**
 * Premium UI JavaScript - 3D Effects and Animations
 * BDU Student Management System - Professional Grade UI
 */

class PremiumUI {
    constructor() {
        this.init();
    }

    init() {
        this.setupCustomCursor();
        this.setup3DTilt();
        this.setupScrollReveal();
        this.setupLiquidButtons();
        this.setupFloatingElements();
        this.setupIntersectionObserver();
    }

    // Custom Blob Cursor with Purple Glow - DRAMATICALLY ENHANCED
    setupCustomCursor() {
        // Create cursor elements
        const cursorBlob = document.createElement('div');
        cursorBlob.className = 'cursor-blob';
        document.body.appendChild(cursorBlob);

        const cursorGlow = document.createElement('div');
        cursorGlow.className = 'cursor-glow';
        document.body.appendChild(cursorGlow);

        // Create cursor trail elements
        const cursorTrails = [];
        for (let i = 0; i < 5; i++) {
            const trail = document.createElement('div');
            trail.className = 'cursor-trail';
            document.body.appendChild(trail);
            cursorTrails.push(trail);
        }

        // Track mouse movement
        let mouseX = 0, mouseY = 0;
        let currentX = 0, currentY = 0;
        let glowCurrentX = 0, glowCurrentY = 0;
        let trailPositions = [];

        document.addEventListener('mousemove', (e) => {
            mouseX = e.clientX;
            mouseY = e.clientY;
            
            // Add to trail positions
            trailPositions.push({ x: mouseX, y: mouseY, time: Date.now() });
            if (trailPositions.length > 5) {
                trailPositions.shift();
            }
        });

        // Smooth cursor animation
        const animateCursor = () => {
            // Blob cursor (fast response)
            currentX += (mouseX - currentX) * 0.3;
            currentY += (mouseY - currentY) * 0.3;
            cursorBlob.style.left = currentX + 'px';
            cursorBlob.style.top = currentY + 'px';

            // Glow cursor (slower, smoother)
            glowCurrentX += (mouseX - glowCurrentX) * 0.15;
            glowCurrentY += (mouseY - glowCurrentY) * 0.15;
            cursorGlow.style.left = glowCurrentX + 'px';
            cursorGlow.style.top = glowCurrentY + 'px';

            // Animate trails
            cursorTrails.forEach((trail, index) => {
                if (trailPositions[index]) {
                    const pos = trailPositions[index];
                    const age = Date.now() - pos.time;
                    const opacity = Math.max(0, 1 - age / 500);
                    
                    trail.style.left = pos.x + 'px';
                    trail.style.top = pos.y + 'px';
                    trail.style.opacity = opacity * 0.5;
                }
            });

            requestAnimationFrame(animateCursor);
        };

        animateCursor();

        // Hide cursor when leaving window
        document.addEventListener('mouseleave', () => {
            cursorBlob.style.opacity = '0';
            cursorGlow.style.opacity = '0';
            cursorTrails.forEach(trail => trail.style.opacity = '0');
        });

        document.addEventListener('mouseenter', () => {
            cursorBlob.style.opacity = '1';
            cursorGlow.style.opacity = '1';
        });
    }

    // 3D Tilt Effects on Cards and Icons
    setup3DTilt() {
        const tiltElements = document.querySelectorAll('.card-3d, .stat-card-3d, .tilt-3d');

        tiltElements.forEach(element => {
            element.addEventListener('mousemove', (e) => {
                const rect = element.getBoundingClientRect();
                const x = e.clientX - rect.left;
                const y = e.clientY - rect.top;
                
                const centerX = rect.width / 2;
                const centerY = rect.height / 2;
                
                const rotateX = (y - centerY) / 10;
                const rotateY = (centerX - x) / 10;
                
                element.style.transform = `perspective(1000px) rotateX(${rotateX}deg) rotateY(${rotateY}deg) scale(1.02) translateZ(20px)`;
            });

            element.addEventListener('mouseleave', () => {
                element.style.transform = 'perspective(1000px) rotateX(0) rotateY(0) scale(1) translateZ(0)';
            });
        });
    }

    // Intersection Observer for Scroll Reveal Animations
    setupIntersectionObserver() {
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('active');
                    
                    // Stagger animation for multiple elements
                    const delay = entry.target.dataset.delay || 0;
                    entry.target.style.transitionDelay = `${delay}ms`;
                }
            });
        }, observerOptions);

        // Observe reveal elements
        document.querySelectorAll('.reveal, .reveal-spring').forEach(element => {
            observer.observe(element);
        });
    }

    // Liquid Fill Animation for Buttons
    setupLiquidButtons() {
        const liquidButtons = document.querySelectorAll('.btn-liquid');

        liquidButtons.forEach(button => {
            button.addEventListener('mouseenter', (e) => {
                const rect = button.getBoundingClientRect();
                const x = e.clientX - rect.left;
                const y = e.clientY - rect.top;
                
                const ripple = document.createElement('span');
                ripple.style.position = 'absolute';
                ripple.style.width = '0';
                ripple.style.height = '0';
                ripple.style.borderRadius = '50%';
                ripple.style.background = 'rgba(255, 255, 255, 0.3)';
                ripple.style.transform = 'translate(-50%, -50%)';
                ripple.style.left = x + 'px';
                ripple.style.top = y + 'px';
                ripple.style.transition = 'width 0.6s ease, height 0.6s ease';
                
                button.appendChild(ripple);
                
                setTimeout(() => {
                    ripple.style.width = '300px';
                    ripple.style.height = '300px';
                }, 10);
                
                setTimeout(() => {
                    ripple.remove();
                }, 600);
            });
        });
    }

    // Floating Elements Animation
    setupFloatingElements() {
        const floatingElements = document.querySelectorAll('.cube-3d, .animate-float');

        floatingElements.forEach(element => {
            // Add random animation delay for natural movement
            const delay = Math.random() * 2;
            element.style.animationDelay = `${delay}s`;
        });
    }

    // Enhanced Scroll Reveal with Spring Motion
    setupScrollReveal() {
        const sections = document.querySelectorAll('main > .card, .stats-grid, .chart-container');

        sections.forEach((section, index) => {
            section.classList.add('reveal-spring');
            section.dataset.delay = index * 100; // Stagger animations
        });
    }

    // 3D Cube Animation Enhancement
    enhance3DCube() {
        const cubes = document.querySelectorAll('.cube-3d');

        cubes.forEach(cube => {
            // Add random rotation speed
            const speed = 3 + Math.random() * 3;
            cube.style.animationDuration = `${speed}s`;
            
            // Add hover interaction
            cube.addEventListener('mouseenter', () => {
                cube.style.animationPlayState = 'paused';
                cube.style.transform = 'scale(1.2) rotateX(45deg) rotateY(45deg)';
            });

            cube.addEventListener('mouseleave', () => {
                cube.style.animationPlayState = 'running';
                cube.style.transform = '';
            });
        });
    }

    // Premium Loading Animation
    showPremiumLoading(container) {
        const loadingHTML = `
            <div class="loading-3d">
                <div class="loading-cube">
                    <div class="face front"></div>
                    <div class="face back"></div>
                    <div class="face right"></div>
                    <div class="face left"></div>
                    <div class="face top"></div>
                    <div class="face bottom"></div>
                </div>
            </div>
        `;
        
        container.innerHTML = loadingHTML;
    }

    // Particle Effect for Background
    createParticleEffect() {
        const particleContainer = document.createElement('div');
        particleContainer.style.position = 'fixed';
        particleContainer.style.top = '0';
        particleContainer.style.left = '0';
        particleContainer.style.width = '100%';
        particleContainer.style.height = '100%';
        particleContainer.style.pointerEvents = 'none';
        particleContainer.style.zIndex = '1';
        document.body.appendChild(particleContainer);

        for (let i = 0; i < 50; i++) {
            const particle = document.createElement('div');
            particle.style.position = 'absolute';
            particle.style.width = '2px';
            particle.style.height = '2px';
            particle.style.background = 'rgba(139, 92, 246, 0.5)';
            particle.style.borderRadius = '50%';
            particle.style.left = Math.random() * 100 + '%';
            particle.style.top = Math.random() * 100 + '%';
            particle.style.animation = `float ${5 + Math.random() * 10}s ease-in-out infinite`;
            particle.style.animationDelay = Math.random() * 5 + 's';
            
            particleContainer.appendChild(particle);
        }
    }

    // Premium Alert System
    showPremiumAlert(message, type = 'info') {
        const alert = document.createElement('div');
        alert.className = `alert-3d alert-${type}-3d reveal-spring`;
        alert.innerHTML = `
            <div class="alert-content">
                <span class="alert-icon">${this.getAlertIcon(type)}</span>
                <span class="alert-message">${message}</span>
                <button class="alert-close" onclick="this.parentElement.parentElement.remove()">×</button>
            </div>
        `;
        
        document.body.appendChild(alert);
        
        // Trigger reveal animation
        setTimeout(() => alert.classList.add('active'), 10);
        
        // Auto-remove after 5 seconds
        setTimeout(() => {
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 300);
        }, 5000);
    }

    getAlertIcon(type) {
        const icons = {
            success: '✅',
            error: '❌',
            warning: '⚠️',
            info: 'ℹ️'
        };
        return icons[type] || icons.info;
    }

    // Premium Form Validation
    setupPremiumValidation() {
        const forms = document.querySelectorAll('form');

        forms.forEach(form => {
            form.addEventListener('submit', (e) => {
                const inputs = form.querySelectorAll('input[required], select[required], textarea[required]');
                let isValid = true;

                inputs.forEach(input => {
                    if (!input.value.trim()) {
                        this.showFieldError(input, 'This field is required');
                        isValid = false;
                    } else {
                        this.clearFieldError(input);
                    }
                });

                if (!isValid) {
                    e.preventDefault();
                }
            });
        });
    }

    showFieldError(field, message) {
        field.style.borderColor = 'var(--danger)';
        field.style.boxShadow = '0 0 10px rgba(239, 68, 68, 0.3)';
        
        let errorElement = field.parentNode.querySelector('.field-error');
        if (!errorElement) {
            errorElement = document.createElement('div');
            errorElement.className = 'field-error';
            errorElement.style.color = 'var(--danger)';
            errorElement.style.fontSize = '0.875rem';
            errorElement.style.marginTop = '0.25rem';
            field.parentNode.appendChild(errorElement);
        }
        
        errorElement.textContent = message;
    }

    clearFieldError(field) {
        field.style.borderColor = '';
        field.style.boxShadow = '';
        
        const errorElement = field.parentNode.querySelector('.field-error');
        if (errorElement) {
            errorElement.remove();
        }
    }

    // Premium Chart Enhancement
    enhanceCharts() {
        const charts = document.querySelectorAll('canvas');
        
        charts.forEach(chart => {
            const container = chart.closest('.chart-container');
            if (container) {
                container.classList.add('chart-container-3d');
            }
        });
    }

    // Initialize all premium features
    initializeAll() {
        this.enhance3DCube();
        this.createParticleEffect();
        this.createBackgroundParticles();
        this.createParallaxBackground();
        this.createFloatingShapes();
        this.setupSidebarAnimations();
        this.setupPremiumValidation();
        this.enhanceCharts();
        this.enhanceScrollReveal();
        this.showPremiumAlert('🚀 Premium UI initialized successfully!', 'success');
    }

    // Setup Sidebar Animations
    setupSidebarAnimations() {
        // Add animation delays to navigation items
        const navItems = document.querySelectorAll('.sidebar-nav-3d a');
        navItems.forEach((item, index) => {
            item.style.setProperty('--item-index', index);
        });

        // Add animation delays to activity items
        const activityItems = document.querySelectorAll('.activity-item');
        activityItems.forEach((item, index) => {
            item.style.setProperty('--item-index', index);
        });

        // Add hover effects to sidebar
        const sidebar = document.querySelector('.sidebar-3d');
        if (sidebar) {
            sidebar.addEventListener('mouseenter', () => {
                sidebar.style.transform = 'perspective(1000px) rotateY(-2deg) translateZ(5px)';
            });

            sidebar.addEventListener('mouseleave', () => {
                sidebar.style.transform = 'perspective(1000px) rotateY(-5deg)';
            });
        }

        // Add hover effects to activity sidebar
        const activitySidebar = document.querySelector('.activity-log');
        if (activitySidebar) {
            activitySidebar.addEventListener('mouseenter', () => {
                activitySidebar.style.transform = 'translateZ(10px) scale(1.02)';
            });

            activitySidebar.addEventListener('mouseleave', () => {
                activitySidebar.style.transform = 'translateZ(0) scale(1)';
            });
        }
    }

    // Create 3D Parallax Background
    createParallaxBackground() {
        const parallaxContainer = document.createElement('div');
        parallaxContainer.className = 'parallax-3d';
        document.body.appendChild(parallaxContainer);

        const parallaxLayer = document.createElement('div');
        parallaxLayer.className = 'parallax-layer';
        parallaxContainer.appendChild(parallaxLayer);

        // Add mouse-based parallax effect
        document.addEventListener('mousemove', (e) => {
            const x = (e.clientX / window.innerWidth - 0.5) * 20;
            const y = (e.clientY / window.innerHeight - 0.5) * 20;
            
            parallaxLayer.style.transform = `translateX(${x}px) translateY(${y}px) rotate(${x * 0.1}deg)`;
        });
    }

    // Create Floating 3D Shapes
    createFloatingShapes() {
        const shapesContainer = document.createElement('div');
        shapesContainer.style.position = 'fixed';
        shapesContainer.style.top = '0';
        shapesContainer.style.left = '0';
        shapesContainer.style.width = '100%';
        shapesContainer.style.height = '100%';
        shapesContainer.style.pointerEvents = 'none';
        shapesContainer.style.zIndex = '1';
        document.body.appendChild(shapesContainer);

        // Create multiple floating shapes
        for (let i = 0; i < 8; i++) {
            const shape = document.createElement('div');
            const shapeType = Math.random() > 0.5 ? 'sphere' : 'pyramid';
            
            shape.className = `${shapeType}-3d`;
            shape.style.position = 'absolute';
            
            // Random position
            const left = Math.random() * 80 + 10; // 10-90% to avoid edges
            const top = Math.random() * 80 + 10;
            shape.style.left = left + '%';
            shape.style.top = top + '%';
            
            // Random size
            const scale = Math.random() * 0.5 + 0.3; // 0.3-0.8 scale
            shape.style.transform = `scale(${scale})`;
            
            // Random animation delay
            const delay = Math.random() * 10;
            shape.style.animationDelay = delay + 's';
            
            // Add shape faces
            if (shapeType === 'sphere') {
                for (let j = 0; j < 6; j++) {
                    const face = document.createElement('div');
                    face.className = 'sphere-face';
                    shape.appendChild(face);
                }
            } else {
                const faces = ['front', 'back', 'left', 'right'];
                faces.forEach(faceType => {
                    const face = document.createElement('div');
                    face.className = `pyramid-face ${faceType}`;
                    shape.appendChild(face);
                });
            }
            
            shapesContainer.appendChild(shape);
        }
    }

    // Create Background Particle Effects
    createBackgroundParticles() {
        const particleContainer = document.createElement('div');
        particleContainer.className = 'particle-background';
        document.body.appendChild(particleContainer);

        // Create multiple particles
        for (let i = 0; i < 20; i++) {
            const particle = document.createElement('div');
            particle.className = 'particle';
            
            // Random properties
            const size = Math.random() * 6 + 2;
            const left = Math.random() * 100;
            const delay = Math.random() * 10;
            const duration = Math.random() * 20 + 10;
            
            particle.style.width = size + 'px';
            particle.style.height = size + 'px';
            particle.style.left = left + '%';
            particle.style.animationDelay = delay + 's';
            particle.style.animationDuration = duration + 's';
            
            // Random color from theme
            const colors = ['#8B5CF6', '#EC4899', '#10B981', '#F59E0B', '#3B82F6'];
            const color = colors[Math.floor(Math.random() * colors.length)];
            particle.style.background = `radial-gradient(circle, ${color} 0%, transparent 70%)`;
            
            particleContainer.appendChild(particle);
        }
    }

    // Enhanced Scroll Reveal
    enhanceScrollReveal() {
        const sections = document.querySelectorAll('main > .card, .stats-grid, .chart-container, .student-profile');
        
        sections.forEach((section, index) => {
            section.classList.add('reveal-dramatic');
            section.dataset.delay = index * 100; // Stagger animations
        });

        // Enhanced observer for dramatic reveals
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -100px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('active');
                    
                    // Add dramatic entrance effect
                    entry.target.style.transform = 'scale(1.05)';
                    setTimeout(() => {
                        entry.target.style.transform = 'scale(1)';
                    }, 300);
                }
            });
        }, observerOptions);

        document.querySelectorAll('.reveal-dramatic').forEach(element => {
            observer.observe(element);
        });
    }
}

// Initialize Premium UI when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    const premiumUI = new PremiumUI();
    
    // Initialize after a short delay to ensure all elements are loaded
    setTimeout(() => {
        premiumUI.initializeAll();
    }, 500);
});

// Export for global access
window.PremiumUI = PremiumUI;
