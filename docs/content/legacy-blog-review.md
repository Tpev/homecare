# Legacy blog disposition — 2026-08-12

The previous runtime read 21 DOCX files directly from `blogs/`, flattened document structure, assigned request-time dates, and exposed partially excluded files to sitemap and metadata paths. That runtime has been removed. None of these files is public in the new system unless it is deliberately imported, rewritten, independently reviewed, and published from the CMS.

## Immediate disposition

- All legacy DOCX articles: quarantined from public discovery; import creates `noindex,nofollow` drafts only.
- ASC vendor negotiation and clinical workflow market articles: out of LoLo Care’s current audience and positioning; do not republish without a documented strategic reason.
- Third-party brand or facility articles (Angels of Care, Platinum Home Care, Anchor Health, UNC Homecare, Northern Wake Senior Center): high verification, trademark, freshness, and implied-endorsement risk; replace with original neutral resources where a real reader need exists.
- Surgery recovery, pediatric home health, home health service, pricing, and housing articles: high-trust topics; require current primary sources, explicit non-medical boundaries, a qualified independent reviewer, and local verification.
- Generic companion care, caregiver careers, personal care attendant, local agency, and care-coordination articles: candidates for complete rewrites around LoLo’s actual audience, original expertise, local evidence, and a distinct search intent.
- Duplicate “after surgery home care near me” files: consolidate into a single canonical concept if the topic survives review.

## Import and release procedure

1. Run `php artisan content:import-docx blogs --user=<publisher-user-id> --dry-run` and inspect warnings.
2. Import selected files without `--dry-run`. They remain noindex drafts with no inferred publication date.
3. Replace unsupported claims, add primary sources, name the author, add licensed media and alt text, and complete the editorial checklist.
4. Submit to an independent reviewer, then schedule or publish. The live date begins only at actual CMS publication.
5. Map any intentionally retained old URL to the new canonical URL with a permanent redirect. Archive rejected imports.
