import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults } from './../../wayfinder'
/**
* @see \App\Http\Controllers\StoryController::index
 * @see app/Http/Controllers/StoryController.php:45
 * @route '/cerita'
 */
export const index = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/cerita',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\StoryController::index
 * @see app/Http/Controllers/StoryController.php:45
 * @route '/cerita'
 */
index.url = (options?: RouteQueryOptions) => {
    return index.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\StoryController::index
 * @see app/Http/Controllers/StoryController.php:45
 * @route '/cerita'
 */
index.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\StoryController::index
 * @see app/Http/Controllers/StoryController.php:45
 * @route '/cerita'
 */
index.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\StoryController::index
 * @see app/Http/Controllers/StoryController.php:45
 * @route '/cerita'
 */
    const indexForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: index.url(options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\StoryController::index
 * @see app/Http/Controllers/StoryController.php:45
 * @route '/cerita'
 */
        indexForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url(options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\StoryController::index
 * @see app/Http/Controllers/StoryController.php:45
 * @route '/cerita'
 */
        indexForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url({
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    index.form = indexForm
/**
* @see \App\Http\Controllers\StoryController::show
 * @see app/Http/Controllers/StoryController.php:72
 * @route '/cerita/{story}'
 */
export const show = (args: { story: number | { id: number } } | [story: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/cerita/{story}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\StoryController::show
 * @see app/Http/Controllers/StoryController.php:72
 * @route '/cerita/{story}'
 */
show.url = (args: { story: number | { id: number } } | [story: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { story: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'id' in args) {
            args = { story: args.id }
        }
    
    if (Array.isArray(args)) {
        args = {
                    story: args[0],
                }
    }

    args = applyUrlDefaults(args)

    const parsedArgs = {
                        story: typeof args.story === 'object'
                ? args.story.id
                : args.story,
                }

    return show.definition.url
            .replace('{story}', parsedArgs.story.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\StoryController::show
 * @see app/Http/Controllers/StoryController.php:72
 * @route '/cerita/{story}'
 */
show.get = (args: { story: number | { id: number } } | [story: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\StoryController::show
 * @see app/Http/Controllers/StoryController.php:72
 * @route '/cerita/{story}'
 */
show.head = (args: { story: number | { id: number } } | [story: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\StoryController::show
 * @see app/Http/Controllers/StoryController.php:72
 * @route '/cerita/{story}'
 */
    const showForm = (args: { story: number | { id: number } } | [story: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: show.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\StoryController::show
 * @see app/Http/Controllers/StoryController.php:72
 * @route '/cerita/{story}'
 */
        showForm.get = (args: { story: number | { id: number } } | [story: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: show.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\StoryController::show
 * @see app/Http/Controllers/StoryController.php:72
 * @route '/cerita/{story}'
 */
        showForm.head = (args: { story: number | { id: number } } | [story: number | { id: number } ] | number | { id: number }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: show.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    show.form = showForm
const cerita = {
    index: Object.assign(index, index),
show: Object.assign(show, show),
}

export default cerita