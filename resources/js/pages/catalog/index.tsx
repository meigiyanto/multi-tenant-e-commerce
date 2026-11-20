import { Form, Head, Link } from '@inertiajs/react';
import { ArrowLeft, ImagePlus, PackagePlus, Plus, Trash2 } from 'lucide-react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Category = {
    id: number; name: string; slug: string; is_active: boolean; parent_id: number | null; products_count: number
};
type Product = {
    id: number; name: string; slug: string; status: string; price: string; category: string | null; image_url: string | null; variants_count: number; stock: number
};
type Props = {
    team: {
        name: string; slug: string
    };
    store: {
        id: number; name: string; currency: string
    };
    products: {
        data: Product[]; links: {
            url: string | null; label: string; active: boolean
        }[]
    };
    categories: Category[];
    canManage: boolean;
};

export default function CatalogIndex({ team, store, products, categories, canManage }: Props) {
    const base = '/settings/teams/' + team.slug + '/stores/' + store.id + '/catalog';
    return (
        <>
            <Head title={'Catalog — ' + store.name} />
            <div className="flex flex-col gap-6">
                <div className="m-2">
                    <Button asChild size="sm" variant="ghost">
                        <Link href={'/settings/teams/' + team.slug + '/stores/' + store.id}>
                            <ArrowLeft /> Back to store
                        </Link>
                    </Button>
                    <div className="mt-3 flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
                        <Heading title="Product catalog" description={'Manage products, categories, variants, photos, prices, and stock for ' + store.name + '.'} />
                        {canManage ? <Button asChild>
                                <Link href={base + '/products/create'}>
                                    <Plus /> Add product</Link>
                            </Button>
                        : null
                        }
                    </div>
                </div>
                    
                <section className="rounded-md border p-4 m-2">
                    <div className="mb-4 flex items-center gap-2">
                        <PackagePlus className="h-5 w-5" />
                        <h2 className="font-semibold">Categories</h2>
                    </div>
                    {categories.length ? <div className="mb-4 flex flex-wrap gap-2">
                        {categories.map((category) => <div key={category.id} className="flex flex-col gap-2 rounded-sm border p-3 text-sm">
                            <div className="flex items-center gap-2">
                                <span className="font-medium">{category.name}</span>
                                <Badge variant="secondary">{category.products_count}</Badge>
                            </div>
                            {canManage ? <div className="flex flex-wrap items-center gap-2">
                                <Form action={base + '/categories/' + category.id} method="patch" className="flex flex-wrap items-center gap-2">
                                    <Input name="name" defaultValue={category.name} aria-label="Category name" className="h-8 w-36" />
                                    <input type="hidden" name="is_active" value={category.is_active ? '1': '0'} />
                                    <Button size="sm" variant="outline" type="submit">Save</Button>
                                </Form>
                                <Form action={base + '/categories/' + category.id} method="delete">
                                    <Button size="sm" variant="ghost" type="submit" aria-label={'Delete ' + category.name}>
                                    <Trash2 className="h-3.5 w-3.5" />
                                </Button>
                            </Form>
                            </div>: null}
                        </div>
                    )}
                    </div>: <p className="mb-4 text-sm text-muted-foreground">
                        No categories yet.
                    </p>
                }
                    {canManage ? <Form action={base + '/categories'} method="post" className="m-2 grid gap-3 sm:grid-cols-[1fr_1fr_auto]">
                            <div className="grid gap-1">
                                <Label htmlFor="category-name">Category name</Label>
                                <Input id="category-name" name="name" placeholder="e.g. Clothing" required maxLength={120} />
                            </div>
                            <div className="grid gap-1">
                                <Label htmlFor="category-description">Description</Label>
                                <Input id="category-description" name="description" placeholder="Optional description" />
                            </div>
                            <div className="flex items-end">
                                <Button type="submit"><Plus /> Add category</Button>
                            </div>
                        </Form>
                    : null
                    }
                </section>
                
                <section className="overflow-hidden rounded-md border m-2">
                    <div className="flex items-center justify-between border-b p-2">
                        <h2 className="font-semibold">Products</h2><span className="text-sm text-muted-foreground">{products.data.length} shown</span>
                    </div>
                    {products.data.length ? <div className="divide-y">
                        {products.data.map((product) => <div key={product.id} className="flex flex-col gap-3 p-4 sm:flex-row sm:items-center">
                            <div className="flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-sm bg-muted">
                                {product.image_url ? <img src={product.image_url} alt={product.name} className="h-full w-full object-cover" />: <ImagePlus className="h-6 w-6 text-muted-foreground" />}
                            </div>
                            <div className="min-w-0 flex-1">
                                <p className="font-medium">
                                    {product.name}
                                </p>
                                <p className="text-sm text-muted-foreground">
                                    {product.category ?? 'Uncategorized'} · {product.variants_count} variant(s) · Stock {product.stock}
                                </p>
                                <p className="text-sm font-medium">
                                    {store.currency} {Number(product.price).toLocaleString()}
                                </p>
                            </div>
                            <Badge variant={product.status === 'active' ? 'default': 'secondary'}>{product.status}</Badge>
                            {canManage ? <div className="flex gap-2">
                                <Button asChild size="sm" variant="outline">
                                    <Link href={base + '/products/' + product.id + '/edit'}>Manage</Link></Button>
                                    <Form action={base + '/products/' + product.id} method="delete">
                                        <Button size="sm" variant="ghost" type="submit">Archive</Button>
                                    </Form>
                            </div>: null}
                        </div>
                    )}
                </div> : <div className="p-10 text-center">
                    <PackagePlus className="mx-auto mb-3 h-8 w-8 text-muted-foreground" /><p className="font-medium">
                        No products yet
                    </p>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Create your first product to start building this Store's catalog.
                    </p>
                </div>
                }
                {products.links.length > 3 ? <div className="flex flex-wrap gap-2 border-t p-3">
                    {products.links.map((link, i) => <Button key={i} asChild size="sm" variant={link.active ? 'default': 'outline'} disabled={!link.url}><a href={link.url ?? '#'} dangerouslySetInnerHTML={ { __html: link.label }} /></Button>)}
                </div>: null}
            </section>
            </div>
        </>
    );
}

CatalogIndex.layout = (props: Props) => ({
    breadcrumbs: [{
        title: 'Teams', href: '/settings/teams'
    }, {
        title: props.team.name, href: '/settings/teams/' + props.team.slug
    }, {
        title: 'Stores', href: '/settings/teams/' + props.team.slug + '/stores'
    }, {
        title: props.store.name, href: '/settings/teams/' + props.team.slug + '/stores/' + props.store.id
    }, {
        title: 'Catalog', href: '/settings/teams/' + props.team.slug + '/stores/' + props.store.id + '/catalog'
    }]
});