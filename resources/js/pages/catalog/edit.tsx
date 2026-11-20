import { Form, Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { ArrowLeft, Plus, Trash2 } from 'lucide-react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type VariantRow = { id?: number; name: string; sku: string; option: string; value: string; option2: string; value2: string; price: string; initial_stock: string; low_stock_threshold: string };
type Product = { id: number; name: string; slug: string; status: string; category_id: number | null; description: string | null; price: string; compare_at_price: string | null; track_inventory: boolean; variants: { id: number; name: string; sku: string; options: Record<string, string>; price: string | null; initial_stock: string; low_stock_threshold: string }[]; images: { id: number; url: string; alt_text: string | null; is_primary: boolean }[] };
type Movement = { id: number; variant: string | null; sku: string | null; type: string; quantity: number; balance_after: number; notes: string | null; user: string | null; created_at: string | null };
type Props = { movements: Movement[]; team: { name: string; slug: string }; store: { id: number; name: string; currency: string }; categories: { id: number; name: string }[]; product: Product };

export default function CatalogEdit({ team, store, categories, product, movements }: Props) {
    const base = '/settings/teams/' + team.slug + '/stores/' + store.id + '/catalog';
    const [variants, setVariants] = useState<VariantRow[]>(product.variants.map((v) => ({ id: v.id, name: v.name, sku: v.sku, option: Object.keys(v.options ?? {})[0] ?? '', value: Object.values(v.options ?? {})[0] ?? '', option2: Object.keys(v.options ?? {})[1] ?? '', value2: Object.values(v.options ?? {})[1] ?? '', price: v.price ?? '', initial_stock: v.initial_stock, low_stock_threshold: v.low_stock_threshold })));
    const update = (index: number, key: keyof VariantRow, value: string) => setVariants((rows) => rows.map((row, i) => i === index ? { ...row, [key]: value } : row));
    return (
        <>
            <Head title={'Edit ' + product.name} />
            <div className="mx-auto flex w-full max-w-4xl flex-col gap-6">
                <Button asChild size="sm" variant="ghost" className="self-start"><Link href={base}><ArrowLeft /> Back to catalog</Link></Button>
                <Heading title="Edit product" description={'Update ' + product.name + ', its variants, images, prices, and inventory.'} />
                <Form action={base + '/products/' + product.id} method="patch" encType="multipart/form-data" className="space-y-6">
                    {({ errors, processing }) => <>
                        <section className="grid gap-4 rounded-md border p-5">
                            <h2 className="font-semibold">Product details</h2>
                            <div className="grid gap-2"><Label htmlFor="name">Product name</Label><Input id="name" name="name" defaultValue={product.name} required maxLength={180} /><InputError message={errors.name} /></div>
                            <div className="grid gap-2"><Label htmlFor="slug">URL slug</Label><Input id="slug" name="slug" defaultValue={product.slug} required /><InputError message={errors.slug} /></div>
                            <div className="grid gap-2"><Label htmlFor="category_id">Category</Label><select id="category_id" name="category_id" defaultValue={product.category_id ?? ''} className="h-10 rounded-sm border bg-background px-3 text-sm"><option value="">Uncategorized</option>{categories.map((category) => <option key={category.id} value={category.id}>{category.name}</option>)}</select></div>
                            <div className="grid gap-2"><Label htmlFor="description">Description</Label><textarea id="description" name="description" defaultValue={product.description ?? ''} rows={5} className="min-h-28 w-full rounded-sm border bg-background px-3 py-2 text-sm" /></div>
                            <div className="grid gap-3 sm:grid-cols-2"><div className="grid gap-2"><Label htmlFor="price">Base price ({store.currency})</Label><Input id="price" name="price" type="number" min="0" step="0.01" defaultValue={product.price} required /></div><div className="grid gap-2"><Label htmlFor="compare_at_price">Compare-at price</Label><Input id="compare_at_price" name="compare_at_price" type="number" min="0" step="0.01" defaultValue={product.compare_at_price ?? ''} /></div></div>
                            <div className="grid gap-2"><Label htmlFor="status">Status</Label><select id="status" name="status" defaultValue={product.status} className="h-10 rounded-sm border bg-background px-3 text-sm"><option value="draft">Draft</option><option value="active">Active</option><option value="archived">Archived</option></select></div>
                            <label className="flex items-center gap-2 text-sm"><input type="hidden" name="track_inventory" value="0" /><input type="checkbox" name="track_inventory" value="1" defaultChecked={product.track_inventory} /> Track inventory</label>
                        </section>
                        <section className="space-y-4 rounded-md border p-5">
                            <div className="flex items-center justify-between"><div><h2 className="font-semibold">Variants and inventory</h2><p className="text-sm text-muted-foreground">Update existing variant stock or add a new SKU. Stock changes are logged.</p></div><Button type="button" size="sm" variant="outline" onClick={() => setVariants((rows) => [...rows, { name: 'New variant', sku: '', option: '', value: '', option2: '', value2: '', price: '', initial_stock: '0', low_stock_threshold: '0' }])}><Plus /> Add variant</Button></div>
                            {variants.map((variant, index) => <div key={variant.id ?? 'new-' + index} className="space-y-3 rounded-sm border p-4">
                                <div className="flex items-center justify-between"><h3 className="text-sm font-medium">Variant {index + 1}</h3>{variants.length > 1 ? <Button type="button" size="icon" variant="ghost" onClick={() => variant.id ? router.delete(base + '/products/' + product.id + '/variants/' + variant.id) : setVariants((rows) => rows.filter((_, i) => i !== index))}><Trash2 className="h-4 w-4" /></Button> : null}</div>
                                {variant.id ? <input type="hidden" name={'variants[' + index + '][id]'} value={variant.id} /> : null}
                                <div className="grid gap-3 sm:grid-cols-2"><div className="grid gap-1"><Label>Variant name</Label><Input name={'variants[' + index + '][name]'} value={variant.name} onChange={(e) => update(index, 'name', e.target.value)} required /></div><div className="grid gap-1"><Label>SKU</Label><Input name={'variants[' + index + '][sku]'} value={variant.sku} onChange={(e) => update(index, 'sku', e.target.value)} required /></div></div>
                                <div className="grid gap-3 sm:grid-cols-5"><div className="grid gap-1"><Label>Option name</Label><Input value={variant.option} onChange={(e) => update(index, 'option', e.target.value)} placeholder="Color / Size" /></div><div className="grid gap-1"><Label>Option value</Label><Input name={'variants[' + index + '][options][' + (variant.option || 'Option') + ']'} value={variant.value} onChange={(e) => update(index, 'value', e.target.value)} placeholder="Black / M" /></div><div className="grid gap-1"><Label>Option 2 name</Label><Input value={variant.option2} onChange={(e) => update(index, 'option2', e.target.value)} placeholder="Size" /></div><div className="grid gap-1"><Label>Option 2 value</Label><Input name={'variants[' + index + '][options][' + (variant.option2 || 'Option2') + ']'} value={variant.value2} onChange={(e) => update(index, 'value2', e.target.value)} placeholder="M" /></div><div className="grid gap-1"><Label>Override price</Label><Input name={'variants[' + index + '][price]'} type="number" min="0" step="0.01" value={variant.price} onChange={(e) => update(index, 'price', e.target.value)} placeholder="Use base price" /></div></div>
                                <div className="grid gap-3 sm:grid-cols-2"><div className="grid gap-1"><Label>Stock quantity</Label><Input name={'variants[' + index + '][initial_stock]'} type="number" min="0" value={variant.initial_stock} onChange={(e) => update(index, 'initial_stock', e.target.value)} /></div><div className="grid gap-1"><Label>Low-stock threshold</Label><Input name={'variants[' + index + '][low_stock_threshold]'} type="number" min="0" value={variant.low_stock_threshold} onChange={(e) => update(index, 'low_stock_threshold', e.target.value)} /></div></div>
                            </div>)}
                            <InputError message={errors.variants} />
                        </section>
                        <section className="space-y-4 rounded-md border p-5"><h2 className="font-semibold">Product photos</h2><div className="grid grid-cols-2 gap-3 sm:grid-cols-4">{product.images.map((image) => <div key={image.id} className="space-y-2"><img src={image.url} alt={image.alt_text ?? product.name} className="aspect-square w-full rounded-sm border object-cover" />{image.is_primary ? <p className="text-xs text-muted-foreground">Primary photo</p> : null}<Button type="button" size="sm" variant="outline" className="w-full" onClick={() => router.delete(base + '/products/' + product.id + '/images/' + image.id)}>Remove</Button></div>)}</div><div className="grid gap-2"><Label htmlFor="images">Add more photos (JPG, PNG, WebP; max 5 MB each)</Label><Input id="images" name="images[]" type="file" accept="image/jpeg,image/png,image/webp" multiple /></div></section>
                        <div className="flex justify-end gap-2"><Button asChild variant="outline"><Link href={base}>Cancel</Link></Button><Button type="submit" disabled={processing}>Save changes</Button></div>
                    </>}
                </Form>
                <section className="space-y-4 rounded-md border p-5">
                    <div><h2 className="font-semibold">Quick stock adjustment</h2><p className="text-sm text-muted-foreground">Use a positive number to add stock and a negative number to reduce it. Every adjustment is recorded.</p></div>
                    {product.variants.map((variant) => <Form key={variant.id} action={base + '/products/' + product.id + '/variants/' + variant.id + '/stock'} method="post" className="grid gap-3 rounded-sm border p-3 sm:grid-cols-[1fr_120px_1fr_auto] sm:items-end">
                        <div><p className="text-sm font-medium">{variant.name}</p><p className="text-xs text-muted-foreground">{variant.sku} · Current stock: {variant.initial_stock}</p></div>
                        <div className="grid gap-1"><Label>Change (+ / −)</Label><Input name="quantity" type="number" step="1" min="-1000000" max="1000000" required /></div>
                        <div className="grid gap-1"><Label>Reason</Label><Input name="notes" maxLength={500} placeholder="e.g. Restock / damaged" /></div>
                        <Button type="submit" size="sm">Adjust</Button>
                    </Form>)}
                </section>
                <section className="space-y-4 rounded-md border p-5">
                    <div><h2 className="font-semibold">Inventory movement history</h2><p className="text-sm text-muted-foreground">Latest 25 movements for this product.</p></div>
                    {movements.length ? <div className="overflow-x-auto"><table className="w-full min-w-[640px] text-left text-sm"><thead><tr className="border-b text-muted-foreground"><th className="p-2 font-medium">Date</th><th className="p-2 font-medium">Variant / SKU</th><th className="p-2 font-medium">Type</th><th className="p-2 font-medium">Change</th><th className="p-2 font-medium">Balance</th><th className="p-2 font-medium">By / Notes</th></tr></thead><tbody>{movements.map((movement) => <tr key={movement.id} className="border-b last:border-0"><td className="p-2 whitespace-nowrap">{movement.created_at ? new Date(movement.created_at).toLocaleString() : '—'}</td><td className="p-2">{movement.variant}<span className="block text-xs text-muted-foreground">{movement.sku}</span></td><td className="p-2">{movement.type}</td><td className={'p-2 font-medium ' + (movement.quantity > 0 ? 'text-emerald-700' : 'text-red-600')}>{movement.quantity > 0 ? '+' : ''}{movement.quantity}</td><td className="p-2">{movement.balance_after}</td><td className="p-2">{movement.user ?? 'System'}{movement.notes ? <span className="block text-xs text-muted-foreground">{movement.notes}</span> : null}</td></tr>)}</tbody></table></div> : <p className="text-sm text-muted-foreground">No stock movements recorded yet.</p>}
                </section>
            </div>
        </>
    );
}
CatalogEdit.layout = (props: Props) => ({ breadcrumbs: [{ title: 'Teams', href: '/settings/teams' }, { title: props.team.name, href: '/settings/teams/' + props.team.slug }, { title: props.store.name, href: '/settings/teams/' + props.team.slug + '/stores/' + props.store.id }, { title: 'Catalog', href: '/settings/teams/' + props.team.slug + '/stores/' + props.store.id + '/catalog' }, { title: 'Edit product', href: '#' }] });
