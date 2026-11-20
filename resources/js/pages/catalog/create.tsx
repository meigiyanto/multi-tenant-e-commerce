import { Form, Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import { ArrowLeft, Plus, Trash2 } from 'lucide-react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type VariantRow = { name: string; sku: string; option: string; value: string; option2: string; value2: string; price: string; initial_stock: string; low_stock_threshold: string };
type Props = { team: { name: string; slug: string }; store: { id: number; name: string; currency: string }; categories: { id: number; name: string }[] };

export default function CatalogCreate({ team, store, categories }: Props) {
    const base = '/settings/teams/' + team.slug + '/stores/' + store.id + '/catalog';
    const [variants, setVariants] = useState<VariantRow[]>([{ name: 'Default', sku: '', option: '', value: '', option2: '', value2: '', price: '', initial_stock: '0', low_stock_threshold: '0' }]);
    const update = (index: number, key: keyof VariantRow, value: string) => setVariants((rows) => rows.map((row, i) => i === index ? { ...row, [key]: value } : row));
    return (
        <>
            <Head title={'Add product — ' + store.name} />
            <div className="mx-auto flex w-full max-w-4xl flex-col gap-6">
                <Button asChild size="sm" variant="ghost" className="self-start"><Link href={base}><ArrowLeft /> Back to catalog</Link></Button>
                <Heading title="Add product" description="Create a product and its Store-specific variants, pricing, photos, and initial inventory." />
                <Form action={base + '/products'} method="post" encType="multipart/form-data" className="space-y-6">
                    {({ errors, processing }) => <>
                        <section className="grid gap-4 rounded-md border p-5">
                            <h2 className="font-semibold">Product details</h2>
                            <div className="grid gap-2"><Label htmlFor="name">Product name</Label><Input id="name" name="name" required maxLength={180} /><InputError message={errors.name} /></div>
                            <div className="grid gap-2"><Label htmlFor="slug">URL slug (optional)</Label><Input id="slug" name="slug" placeholder="generated-from-product-name" /><InputError message={errors.slug} /></div>
                            <div className="grid gap-2"><Label htmlFor="category_id">Category</Label><select id="category_id" name="category_id" className="h-10 rounded-sm border bg-background px-3 text-sm"><option value="">Uncategorized</option>{categories.map((category) => <option key={category.id} value={category.id}>{category.name}</option>)}</select><InputError message={errors.category_id} /></div>
                            <div className="grid gap-2"><Label htmlFor="description">Description</Label><textarea id="description" name="description" rows={5} maxLength={10000} className="min-h-28 w-full rounded-sm border bg-background px-3 py-2 text-sm" /><InputError message={errors.description} /></div>
                            <div className="grid gap-3 sm:grid-cols-2"><div className="grid gap-2"><Label htmlFor="price">Base price ({store.currency})</Label><Input id="price" name="price" type="number" min="0" step="0.01" required /><InputError message={errors.price} /></div><div className="grid gap-2"><Label htmlFor="compare_at_price">Compare-at price</Label><Input id="compare_at_price" name="compare_at_price" type="number" min="0" step="0.01" /><InputError message={errors.compare_at_price} /></div></div>
                            <div className="grid gap-2"><Label htmlFor="status">Status</Label><select id="status" name="status" className="h-10 rounded-sm border bg-background px-3 text-sm"><option value="draft">Draft</option><option value="active">Active</option></select></div>
                            <label className="flex items-center gap-2 text-sm"><input type="hidden" name="track_inventory" value="0" /><input type="checkbox" name="track_inventory" value="1" defaultChecked /> Track inventory</label>
                        </section>
                        <section className="space-y-4 rounded-md border p-5">
                            <div className="flex items-center justify-between"><div><h2 className="font-semibold">Variants and inventory</h2><p className="text-sm text-muted-foreground">Each variant has its own SKU and stock balance.</p></div><Button type="button" size="sm" variant="outline" onClick={() => setVariants((rows) => [...rows, { name: 'New variant', sku: '', option: '', value: '', price: '', initial_stock: '0', low_stock_threshold: '0' }])}><Plus /> Add variant</Button></div>
                            {variants.map((variant, index) => <div key={index} className="space-y-3 rounded-sm border p-4">
                                <div className="flex items-center justify-between"><h3 className="text-sm font-medium">Variant {index + 1}</h3>{variants.length > 1 ? <Button type="button" size="icon" variant="ghost" onClick={() => setVariants((rows) => rows.filter((_, i) => i !== index))}><Trash2 className="h-4 w-4" /></Button> : null}</div>
                                <div className="grid gap-3 sm:grid-cols-2"><div className="grid gap-1"><Label>Variant name</Label><Input name={'variants[' + index + '][name]'} value={variant.name} onChange={(e) => update(index, 'name', e.target.value)} required /></div><div className="grid gap-1"><Label>SKU</Label><Input name={'variants[' + index + '][sku]'} value={variant.sku} onChange={(e) => update(index, 'sku', e.target.value)} required /></div></div>
                                <div className="grid gap-3 sm:grid-cols-5"><div className="grid gap-1"><Label>Option name</Label><Input value={variant.option} onChange={(e) => update(index, 'option', e.target.value)} placeholder="Color / Size" /></div><div className="grid gap-1"><Label>Option value</Label><Input name={'variants[' + index + '][options][' + (variant.option || 'Option') + ']'} value={variant.value} onChange={(e) => update(index, 'value', e.target.value)} placeholder="Black / M" /></div><div className="grid gap-1"><Label>Option 2 name</Label><Input value={variant.option2} onChange={(e) => update(index, 'option2', e.target.value)} placeholder="Size" /></div><div className="grid gap-1"><Label>Option 2 value</Label><Input name={'variants[' + index + '][options][' + (variant.option2 || 'Option2') + ']'} value={variant.value2} onChange={(e) => update(index, 'value2', e.target.value)} placeholder="M" /></div><div className="grid gap-1"><Label>Override price</Label><Input name={'variants[' + index + '][price]'} type="number" min="0" step="0.01" value={variant.price} onChange={(e) => update(index, 'price', e.target.value)} placeholder="Use base price" /></div></div>
                                <div className="grid gap-3 sm:grid-cols-2"><div className="grid gap-1"><Label>Initial stock</Label><Input name={'variants[' + index + '][initial_stock]'} type="number" min="0" value={variant.initial_stock} onChange={(e) => update(index, 'initial_stock', e.target.value)} /></div><div className="grid gap-1"><Label>Low-stock alert threshold</Label><Input name={'variants[' + index + '][low_stock_threshold]'} type="number" min="0" value={variant.low_stock_threshold} onChange={(e) => update(index, 'low_stock_threshold', e.target.value)} /></div></div>
                            </div>)}
                            <InputError message={errors.variants} />
                        </section>
                        <section className="grid gap-3 rounded-md border p-5"><h2 className="font-semibold">Product photos</h2><div className="grid gap-2"><Label htmlFor="images">Upload photos (JPG, PNG, WebP; max 5 MB each; up to 10)</Label><Input id="images" name="images[]" type="file" accept="image/jpeg,image/png,image/webp" multiple /><InputError message={errors.images} /></div></section>
                        <div className="flex justify-end gap-2"><Button asChild variant="outline"><Link href={base}>Cancel</Link></Button><Button type="submit" disabled={processing}>Create product</Button></div>
                    </>}
                </Form>
            </div>
        </>
    );
}
CatalogCreate.layout = (props: Props) => ({ breadcrumbs: [{ title: 'Teams', href: '/settings/teams' }, { title: props.team.name, href: '/settings/teams/' + props.team.slug }, { title: props.store.name, href: '/settings/teams/' + props.team.slug + '/stores/' + props.store.id }, { title: 'Catalog', href: '/settings/teams/' + props.team.slug + '/stores/' + props.store.id + '/catalog' }, { title: 'Add product', href: '#' }] });
