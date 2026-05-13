function parseOptions(trigger) {
    if (!trigger) {
        return {}
    }

    const attributeOptions = {}

    if (trigger.dataset.filamentPopupPosition) {
        attributeOptions.position = trigger.dataset.filamentPopupPosition
    }

    if (trigger.dataset.filamentPopupOffset) {
        attributeOptions.offset = trigger.dataset.filamentPopupOffset
    }

    try {
        return {
            ...JSON.parse(trigger.dataset.filamentPopupOptions || '{}'),
            ...attributeOptions,
        }
    } catch {
        return attributeOptions
    }
}

function keyMatches(requiredKey, event) {
    if (!requiredKey) {
        return true
    }

    const key = requiredKey.toLowerCase()

    if (key === 'shift') {
        return event.shiftKey === true
    }

    if (key === 'alt' || key === 'option') {
        return event.altKey === true
    }

    if (key === 'ctrl' || key === 'control') {
        return event.ctrlKey === true
    }

    if (key === 'meta' || key === 'cmd' || key === 'command') {
        return event.metaKey === true
    }

    return event.key?.toLowerCase() === key
}

function triggerType(trigger, options = parseOptions(trigger)) {
    return trigger?.dataset.filamentPopupTrigger || options.trigger || 'hover'
}

function triggerKey(trigger, options = parseOptions(trigger)) {
    return trigger?.dataset.filamentPopupTriggerKey || options.triggerKey || null
}

function eventMatches(trigger, options, event, expectedTrigger) {
    return triggerType(trigger, options) === expectedTrigger && keyMatches(triggerKey(trigger, options), event)
}

function rectFor(trigger) {
    const rect = trigger.getBoundingClientRect()

    return {
        x: rect.x,
        y: rect.y,
        width: rect.width,
        height: rect.height,
        top: rect.top,
        right: rect.right,
        bottom: rect.bottom,
        left: rect.left,
    }
}

let activeMode = null
let activeOptions = {}
let activeRect = null

function popupPanel() {
    return document.querySelector('[data-filament-popup-panel]')
}

function rectFromPanel(panel) {
    return {
        left: Number(panel.dataset.filamentPopupTriggerLeft || 0),
        top: Number(panel.dataset.filamentPopupTriggerTop || 0),
        right: Number(panel.dataset.filamentPopupTriggerRight || 0),
        bottom: Number(panel.dataset.filamentPopupTriggerBottom || 0),
        width: Number(panel.dataset.filamentPopupTriggerWidth || 0),
        height: Number(panel.dataset.filamentPopupTriggerHeight || 0),
    }
}

function placePanel() {
    const panel = popupPanel()
    const triggerRect = activeRect || (panel ? rectFromPanel(panel) : null)

    if (!panel || !triggerRect) {
        return
    }

    const offset = Number(panel.dataset.filamentPopupOffset ?? activeOptions.offset ?? 8)
    const placement = panel.dataset.filamentPopupPosition || activeOptions.position || 'top-center'
    const [verticalPosition, horizontalPosition = 'center'] = placement.split('-')
    const panelRect = panel.getBoundingClientRect()
    const padding = 8
    let left = triggerRect.left + (triggerRect.width - panelRect.width) / 2
    let top = triggerRect.top - panelRect.height - offset

    if (verticalPosition === 'bottom') {
        top = triggerRect.bottom + offset
    }

    if (verticalPosition === 'middle') {
        top = triggerRect.top + (triggerRect.height - panelRect.height) / 2
    }

    if (verticalPosition === 'middle' && horizontalPosition === 'left') {
        left = triggerRect.left - panelRect.width - offset
    }

    if (verticalPosition === 'middle' && horizontalPosition === 'right') {
        left = triggerRect.right + offset
    }

    if (['top', 'bottom'].includes(verticalPosition)) {
        if (horizontalPosition === 'left') {
            left = triggerRect.left
        }

        if (horizontalPosition === 'right') {
            left = triggerRect.right - panelRect.width
        }
    }

    const maxLeft = Math.max(padding, window.innerWidth - panelRect.width - padding)
    const maxTop = Math.max(padding, window.innerHeight - panelRect.height - padding)

    left = Math.min(Math.max(left, padding), maxLeft)
    top = Math.min(Math.max(top, padding), maxTop)

    panel.style.position = 'fixed'
    panel.style.zIndex = '2147483647'
    panel.style.maxWidth = `calc(100vw - ${padding * 2}px)`
    panel.style.left = `${left}px`
    panel.style.top = `${top}px`
    panel.style.visibility = 'visible'
}

function dispatchOpen(trigger) {
    if (!window.Livewire) {
        return
    }

    const options = parseOptions(trigger)
    const rect = rectFor(trigger)

    activeMode = triggerType(trigger, options)
    activeOptions = options
    activeRect = rect

    window.Livewire.dispatch('filament-popup::open', {
        payload: {
            content: trigger.dataset.filamentPopupContent,
            model: trigger.dataset.filamentPopupModel,
            recordKey: trigger.dataset.filamentPopupRecordKey,
            options,
            rect,
        },
    })

    window.requestAnimationFrame(placePanel)
    window.setTimeout(placePanel, 50)
}

function dispatchClose() {
    if (!window.Livewire) {
        return
    }

    window.Livewire.dispatch('filament-popup::close')
    activeMode = null
    activeOptions = {}
    activeRect = null
}

let openTimer = null
let closeTimer = null
let hoveredTrigger = null

function clearTimers() {
    window.clearTimeout(openTimer)
    window.clearTimeout(closeTimer)
}

function registerLivewirePlacementHooks() {
    if (!window.Livewire || window.__filamentPopupLivewireHooksRegistered) {
        return
    }

    window.__filamentPopupLivewireHooksRegistered = true

    window.Livewire.hook('morph.updated', () => {
        window.requestAnimationFrame(placePanel)
    })

    window.Livewire.hook('commit', ({ succeed }) => {
        if (typeof succeed !== 'function') {
            return
        }

        succeed(() => {
            window.requestAnimationFrame(placePanel)
        })
    })
}

if (!window.__filamentPopupListenersRegistered) {
    window.__filamentPopupListenersRegistered = true

    document.addEventListener(
        'mouseover',
        (event) => {
            const trigger = event.target.closest('[data-filament-popup]')

            if (!trigger) {
                return
            }

            const options = parseOptions(trigger)
            hoveredTrigger = trigger

            if (!eventMatches(trigger, options, event, 'hover')) {
                return
            }

            clearTimers()

            openTimer = window.setTimeout(() => {
                dispatchOpen(trigger)
            }, Number(options.delay ?? 250))
        },
        true,
    )

    document.addEventListener(
        'mouseout',
        (event) => {
            const trigger = event.target.closest('[data-filament-popup]')
            const panel = popupPanel()

            if (!trigger || trigger.contains(event.relatedTarget) || panel?.contains(event.relatedTarget)) {
                return
            }

            const options = parseOptions(trigger)

            if (hoveredTrigger === trigger) {
                hoveredTrigger = null
            }

            window.clearTimeout(openTimer)

            if (triggerType(trigger, options) !== 'hover') {
                return
            }

            closeTimer = window.setTimeout(() => {
                dispatchClose()
            }, Number(options.closeDelay ?? 200))
        },
        true,
    )

    document.addEventListener(
        'click',
        (event) => {
            const trigger = event.target.closest('[data-filament-popup]')
            const panel = popupPanel()

            if (!trigger) {
                if (
                    ['click', 'contextmenu'].includes(activeMode) &&
                    panel &&
                    !panel.contains(event.target)
                ) {
                    dispatchClose()
                }

                return
            }

            const options = parseOptions(trigger)

            if (!eventMatches(trigger, options, event, 'click')) {
                return
            }

            dispatchOpen(trigger)
        },
        true,
    )

    document.addEventListener(
        'contextmenu',
        (event) => {
            const trigger = event.target.closest('[data-filament-popup]')

            if (!trigger) {
                return
            }

            const options = parseOptions(trigger)

            if (!eventMatches(trigger, options, event, 'contextmenu')) {
                return
            }

            event.preventDefault()
            event.stopPropagation()

            dispatchOpen(trigger)
        },
        true,
    )

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            dispatchClose()

            return
        }

        if (!hoveredTrigger) {
            return
        }

        const options = parseOptions(hoveredTrigger)

        if (!eventMatches(hoveredTrigger, options, event, 'hover')) {
            return
        }

        clearTimers()

        openTimer = window.setTimeout(() => {
            dispatchOpen(hoveredTrigger)
        }, Number(options.delay ?? 250))
    })

    document.addEventListener(
        'mouseover',
        (event) => {
            if (popupPanel()?.contains(event.target)) {
                clearTimers()
            }
        },
        true,
    )

    document.addEventListener(
        'mouseout',
        (event) => {
            const panel = popupPanel()

            if (
                !panel ||
                !panel.contains(event.target) ||
                panel.contains(event.relatedTarget) ||
                event.relatedTarget?.closest?.('[data-filament-popup]')
            ) {
                return
            }

            if (activeMode !== 'hover') {
                return
            }

            closeTimer = window.setTimeout(() => {
                dispatchClose()
            }, Number(activeOptions.closeDelay ?? 200))
        },
        true,
    )

    const observer = new MutationObserver(() => {
        window.requestAnimationFrame(placePanel)
    })

    observer.observe(document.body, {
        childList: true,
        subtree: true,
    })

    window.addEventListener('resize', placePanel)
    window.addEventListener('scroll', placePanel, true)
    window.addEventListener('filament-popup::saved', () => {
        if (!window.Livewire) {
            return
        }

        window.Livewire.all()
            .filter((component) => component.name !== 'filament-popup')
            .forEach((component) => component.$wire.$refresh())
    })

    document.addEventListener('livewire:init', registerLivewirePlacementHooks)
    registerLivewirePlacementHooks()
}
