<?php
/**
 * Private document upload and download.
 *
 * @package ThreeRing\DomainManager
 */

declare(strict_types=1);

namespace ThreeRing\DomainManager\Services;

use ThreeRing\DomainManager\Capabilities;
use ThreeRing\DomainManager\Db\Documents_Repository;

defined( 'ABSPATH' ) || exit;

/**
 * Class Document_Service
 */
final class Document_Service {

	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_action( 'admin_post_dm_download_document', array( $this, 'handle_download' ) );
		add_filter( 'wp_get_attachment_url', array( $this, 'filter_private_url' ), 10, 2 );
		add_filter( 'attachment_link', array( $this, 'filter_private_attachment_link' ), 10, 2 );
		add_filter( 'rest_prepare_attachment', array( $this, 'filter_rest_attachment' ), 10, 3 );
	}

	/**
	 * Allowed MIME types.
	 *
	 * @return array<string,string>
	 */
	public static function allowed_mimes(): array {
		return array(
			'pdf'  => 'application/pdf',
			'png'  => 'image/png',
			'jpg'  => 'image/jpeg',
			'jpeg' => 'image/jpeg',
			'webp' => 'image/webp',
			'doc'  => 'application/msword',
			'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
		);
	}

	/**
	 * Upload a private document for a domain.
	 *
	 * @param int    $domain_id Domain ID.
	 * @param array  $file      $_FILES entry.
	 * @param string $title     Document title.
	 * @param string $doc_type  Document type.
	 * @return int|\WP_Error Document row ID.
	 */
	public function upload( int $domain_id, array $file, string $title = '', string $doc_type = 'other' ) {
		if ( empty( $file['tmp_name'] ) ) {
			return new \WP_Error( 'dm_upload', __( 'No file uploaded.', '3ring-domain-manager' ) );
		}

		$settings = get_option( 'dm_settings', array() );
		$max_mb   = ! empty( $settings['max_upload_mb'] ) ? (int) $settings['max_upload_mb'] : 10;
		if ( ! empty( $file['size'] ) && (int) $file['size'] > $max_mb * MB_IN_BYTES ) {
			return new \WP_Error( 'dm_upload', sprintf( /* translators: %d: megabytes */ __( 'File exceeds the maximum size of %d MB.', '3ring-domain-manager' ), $max_mb ) );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$overrides = array(
			'test_form' => false,
			'mimes'     => self::allowed_mimes(),
		);

		$moved = wp_handle_upload( $file, $overrides );
		if ( isset( $moved['error'] ) ) {
			return new \WP_Error( 'dm_upload', $moved['error'] );
		}

		$attachment = array(
			'post_mime_type' => $moved['type'],
			'post_title'     => $title ? sanitize_text_field( $title ) : sanitize_file_name( basename( $moved['file'] ) ),
			'post_content'   => '',
			'post_status'    => 'private',
		);

		$attachment_id = wp_insert_attachment( $attachment, $moved['file'] );
		if ( is_wp_error( $attachment_id ) ) {
			return $attachment_id;
		}

		$meta = wp_generate_attachment_metadata( $attachment_id, $moved['file'] );
		wp_update_attachment_metadata( $attachment_id, $meta );

		update_post_meta( $attachment_id, '_dm_private', 1 );
		update_post_meta( $attachment_id, '_dm_domain_id', $domain_id );

		$doc_id = ( new Documents_Repository() )->insert(
			array(
				'domain_id'     => $domain_id,
				'attachment_id' => $attachment_id,
				'title'         => $attachment['post_title'],
				'doc_type'      => sanitize_key( $doc_type ),
			)
		);

		if ( ! $doc_id ) {
			return new \WP_Error( 'dm_upload', __( 'Could not save document record.', '3ring-domain-manager' ) );
		}

		return $doc_id;
	}

	/**
	 * Build secure download URL.
	 *
	 * @param int $document_id Document row ID.
	 */
	public static function download_url( int $document_id ): string {
		return wp_nonce_url(
			admin_url( 'admin-post.php?action=dm_download_document&id=' . $document_id ),
			'dm_download_document_' . $document_id
		);
	}

	/**
	 * Handle private download.
	 */
	public function handle_download(): void {
		if ( ! Capabilities::current_user_can_view() ) {
			wp_die( esc_html__( 'You do not have permission to download this file.', '3ring-domain-manager' ), 403 );
		}

		$document_id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		if ( ! $document_id || ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'dm_download_document_' . $document_id ) ) {
			wp_die( esc_html__( 'Invalid download request.', '3ring-domain-manager' ), 403 );
		}

		$doc = ( new Documents_Repository() )->get( $document_id );
		if ( ! $doc ) {
			wp_die( esc_html__( 'Document not found.', '3ring-domain-manager' ), 404 );
		}

		$path = get_attached_file( (int) $doc->attachment_id );
		if ( ! $path || ! is_readable( $path ) ) {
			wp_die( esc_html__( 'File not found.', '3ring-domain-manager' ), 404 );
		}

		$mime = get_post_mime_type( (int) $doc->attachment_id ) ?: 'application/octet-stream';
		$filename = basename( $path );

		nocache_headers();
		header( 'Content-Type: ' . $mime );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Content-Length: ' . (string) filesize( $path ) );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
		readfile( $path );
		exit;
	}

	/**
	 * Hide direct public URLs for private attachments.
	 *
	 * @param string $url           Attachment URL.
	 * @param int    $attachment_id Attachment ID.
	 */
	public function filter_private_url( string $url, int $attachment_id ): string {
		if ( get_post_meta( $attachment_id, '_dm_private', true ) ) {
			return '';
		}
		return $url;
	}

	/**
	 * Hide attachment permalink for private files.
	 *
	 * @param string $link Link.
	 * @param int    $post_id Attachment ID.
	 */
	public function filter_private_attachment_link( string $link, int $post_id ): string {
		if ( get_post_meta( $post_id, '_dm_private', true ) ) {
			return '';
		}
		return $link;
	}

	/**
	 * Block REST exposure of private attachments for unauthorized users.
	 *
	 * @param \WP_REST_Response $response Response.
	 * @param \WP_Post          $post     Attachment post.
	 * @param \WP_REST_Request  $request  Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function filter_rest_attachment( $response, $post, $request ) {
		unset( $request );
		if ( get_post_meta( $post->ID, '_dm_private', true ) && ! Capabilities::current_user_can_view() ) {
			return new \WP_Error( 'rest_forbidden', __( 'Private Domain Manager attachment.', '3ring-domain-manager' ), array( 'status' => 403 ) );
		}
		return $response;
	}
}
