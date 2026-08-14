---
paths:
  - 'app/Filament/Resources/**/Schemas/**'
---

# Schemas

## Reuse createOptionForm schemas with an explicit model
When a Select without ->relationship() reuses another resource's form via createOptionForm(), Filament sets the create-option modal schema model to the PARENT form's record (e.g. TrHeader). Any nested Select in the reused form with ->relationship('cate', ...) then throws "relationship [cate] does not exist on [TrHeader]". Fix: pass the model in the closure: ->createOptionForm(fn (Schema $schema) => $schema->model(TbStock::class)->components(TbStockForm::components())).
